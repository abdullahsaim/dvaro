<?php

namespace App\Modules\AI\Context;

use App\Modules\Agreement\Models\Agreement;
use App\Modules\Customer\Models\Customer;
use App\Modules\Fleet\Models\Vehicle;
use App\Modules\Invoice\Models\Invoice;
use App\Modules\Invoice\Models\InvoiceItem;
use App\Modules\Invoice\Models\Payment;
use App\Modules\SaasCore\Models\Tenant;

/**
 * System prompt for AI "Business Intelligence Mode".
 *
 * Builds a FRESH, tenant-scoped data summary on every request (never cached —
 * the figures change) and instructs the model to answer using ONLY that data.
 *
 * TENANT ISOLATION (CLAUDE.md non-negotiable): this runs inside an authenticated
 * tenant request with current_tenant bound, so TenantScope constrains every
 * Eloquent query automatically. We therefore do NOT use withoutGlobalScope here
 * (unlike the public controllers, which must bypass the scope). The one place a
 * raw join leaves the scope behind is the top-vehicles query: TenantScope covers
 * the base `invoice_items` table, but the joined `vehicles` table is filtered by
 * tenant_id EXPLICITLY in the join clause — see the comment there.
 *
 * Money is rendered as AUD (the data context never shows raw cents).
 */
class IntelligenceModeContext
{
    public function systemPrompt(Tenant $tenant): string
    {
        $data = $this->gather($tenant);
        $name = $tenant->name;

        return <<<PROMPT
You are the DVARO Assistant in BUSINESS INTELLIGENCE MODE for the company "{$name}".

You answer questions about this company's rental business using ONLY the data
provided below. This data is a current snapshot of {$name}'s account.

STRICT RULES:
- Use ONLY the figures below. Do not invent, estimate, or assume data you were
  not given. If the answer is not in the data, say you do not have that figure.
- You have access to {$name}'s data ONLY. You cannot see, mention, compare
  against, or expose data from any other company on the platform. If asked about
  another company, refuse — that data is not available to you.
- All monetary values are in Australian Dollars (AUD).
- Be concise and factual. When useful, show the relevant number.

=== {$name} — CURRENT DATA SNAPSHOT ===

FLEET
{$data['fleet']}

CUSTOMERS
{$data['customers']}

AGREEMENTS
- Active or signed agreements: {$data['active_agreements']}

INVOICING & REVENUE
- Outstanding (unpaid) invoice balance: {$data['outstanding']}
- Overdue invoices: {$data['overdue_count']}
- Revenue received this month: {$data['revenue_month']}

TOP VEHICLES BY REVENUE (paid invoices)
{$data['top_vehicles']}

VEHICLES DUE FOR SERVICE (next 30 days)
{$data['service_due']}

RECENT PAYMENTS (last 5)
{$data['recent_payments']}

=== END OF DATA SNAPSHOT ===
PROMPT;
    }

    /**
     * Assemble the tenant-scoped data summary. Every query below is constrained
     * to the bound tenant automatically by TenantScope (no withoutGlobalScope).
     *
     * @return array<string, string>
     */
    private function gather(Tenant $tenant): array
    {
        // ── Fleet: total + per-status breakdown. ──
        $vehicleCounts = Vehicle::query()
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $totalVehicles = (int) $vehicleCounts->sum();
        $fleetLines = ["- Total vehicles: {$totalVehicles}"];
        foreach (Vehicle::STATUSES as $status) {
            $fleetLines[] = '- '.ucfirst($status).': '.(int) ($vehicleCounts[$status] ?? 0);
        }

        // ── Customers. ──
        $totalCustomers = Customer::query()->count();
        $blacklisted = Customer::query()->where('is_blacklisted', true)->count();
        $customerLines = [
            "- Total customers: {$totalCustomers}",
            "- Blacklisted: {$blacklisted}",
        ];

        // ── Active agreements (signed or active). ──
        $activeAgreements = Agreement::query()
            ->whereIn('status', [Agreement::STATUS_SIGNED, Agreement::STATUS_ACTIVE])
            ->count();

        // ── Outstanding balance across open invoices (cents → AUD). ──
        $outstanding = (int) Invoice::query()
            ->whereNotIn('status', [Invoice::STATUS_PAID, Invoice::STATUS_CANCELLED])
            ->selectRaw('COALESCE(SUM(total - paid_amount), 0) as owing')
            ->value('owing');

        $overdueCount = Invoice::query()
            ->where('status', Invoice::STATUS_OVERDUE)
            ->count();

        // ── Revenue this month = payments received since the 1st (cents → AUD). ──
        $revenueMonth = (int) Payment::query()
            ->where('paid_at', '>=', now()->startOfMonth())
            ->sum('amount');

        // ── Top 3 vehicles by revenue (paid invoices). ──
        //
        // TENANT ISOLATION (per the explicit requirement):
        //   - invoice_items is auto-scoped by TenantScope (current_tenant bound;
        //     the scope qualifies its column as `invoice_items.tenant_id`, so
        //     there is no ambiguity in the join). The redundant explicit filter
        //     below is defence-in-depth across both tables.
        //   - whereHas('invoice', …) stays auto-scoped too (Invoice carries
        //     TenantScope), filtering to PAID invoices.
        //   - the joined `vehicles` table is NOT covered by TenantScope (a global
        //     scope never reaches a manually joined table), so tenant_id is
        //     filtered EXPLICITLY in the join clause. This is mandatory.
        // withoutGlobalScope is deliberately NOT used anywhere here.
        $topVehicles = InvoiceItem::query()
            ->where('invoice_items.tenant_id', $tenant->id)
            ->whereHas('invoice', fn ($q) => $q->where('status', Invoice::STATUS_PAID))
            ->join('vehicles', function ($join) use ($tenant) {
                $join->on('invoice_items.vehicle_id', '=', 'vehicles.id')
                    ->where('vehicles.tenant_id', $tenant->id);
            })
            ->whereNull('vehicles.deleted_at')
            ->groupBy('vehicles.id', 'vehicles.registration_number', 'vehicles.make', 'vehicles.model')
            ->selectRaw('vehicles.registration_number, vehicles.make, vehicles.model, SUM(invoice_items.amount) as revenue')
            ->orderByDesc('revenue')
            ->limit(3)
            ->get();

        $topVehicleLines = $topVehicles->isEmpty()
            ? ['- (no paid invoices yet)']
            : $topVehicles->map(fn ($v) => sprintf(
                '- %s (%s %s): %s',
                $v->registration_number,
                $v->make,
                $v->model,
                $this->aud((int) $v->revenue),
            ))->all();

        // ── Vehicles due for service in the next 30 days. ──
        $serviceDue = Vehicle::query()
            ->whereNotNull('next_service_due')
            ->whereBetween('next_service_due', [now()->startOfDay(), now()->addDays(30)->endOfDay()])
            ->orderBy('next_service_due')
            ->get(['registration_number', 'make', 'model', 'next_service_due']);

        $serviceDueLines = $serviceDue->isEmpty()
            ? ['- (none due in the next 30 days)']
            : $serviceDue->map(fn ($v) => sprintf(
                '- %s (%s %s): due %s',
                $v->registration_number,
                $v->make,
                $v->model,
                $v->next_service_due?->format('d/m/Y'),
            ))->all();

        // ── Recent payments (last 5). ──
        $recentPayments = Payment::query()
            ->with('customer:id,name')
            ->latest('paid_at')
            ->limit(5)
            ->get();

        $recentPaymentLines = $recentPayments->isEmpty()
            ? ['- (no payments recorded yet)']
            : $recentPayments->map(fn ($p) => sprintf(
                '- %s — %s on %s',
                $p->customer?->name ?? 'Unknown',
                $this->aud((int) $p->amount),
                $p->paid_at?->format('d/m/Y') ?? '—',
            ))->all();

        return [
            'fleet' => implode("\n", $fleetLines),
            'customers' => implode("\n", $customerLines),
            'active_agreements' => (string) $activeAgreements,
            'outstanding' => $this->aud($outstanding),
            'overdue_count' => (string) $overdueCount,
            'revenue_month' => $this->aud($revenueMonth),
            'top_vehicles' => implode("\n", $topVehicleLines),
            'service_due' => implode("\n", $serviceDueLines),
            'recent_payments' => implode("\n", $recentPaymentLines),
        ];
    }

    /** Format integer cents as an AUD string, e.g. 123456 → "$1,234.56". */
    private function aud(int $cents): string
    {
        return '$'.number_format($cents / 100, 2);
    }
}
