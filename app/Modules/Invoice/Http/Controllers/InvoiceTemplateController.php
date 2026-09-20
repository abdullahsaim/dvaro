<?php

namespace App\Modules\Invoice\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Customer\Models\Customer;
use App\Modules\Invoice\Http\Requests\InvoiceTemplateRequest;
use App\Modules\Invoice\Models\Invoice;
use App\Modules\Invoice\Models\InvoiceItem;
use App\Modules\Invoice\Services\InvoiceTemplateService;
use App\Modules\SaasCore\Models\TenantUser;
use App\Modules\SaasCore\Services\TenantSettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

/**
 * Settings → Invoices: which of the four layouts a company's invoices use,
 * their logo and accent colour, and the wording around the numbers
 * (client feedback #4).
 *
 * Presentation only. Nothing here can change an amount, a due date or a
 * ledger entry — the numbers come from the invoice, always.
 *
 * ──────────────────────────────────────────────────────────────────────────
 * AUTHORIZATION: tenant-wide configuration, so there is no policy/Gate target.
 * Admin or accounts may write (the same pair who own late fees); everyone else
 * reads. Checked against the TENANT guard — never the empty default web guard.
 * ──────────────────────────────────────────────────────────────────────────
 */
class InvoiceTemplateController extends Controller
{
    public function __construct(
        private readonly InvoiceTemplateService $templates,
        private readonly TenantSettingsService $settings,
    ) {}

    public function show(): InertiaResponse
    {
        $tenant = app('current_tenant');
        $all = $this->settings->all($tenant);
        $template = $this->templates->forUi($tenant);

        return Inertia::render('Settings/InvoiceTemplate', [
            'settings' => $template,
            'gstRegistered' => (bool) $all['gst_registered'],
            'layouts' => InvoiceTemplateService::LAYOUTS,
            'limits' => InvoiceTemplateService::LIMITS,
            'logoMaxKb' => InvoiceTemplateRequest::LOGO_MAX_KB,
            'defaults' => InvoiceTemplateService::DEFAULTS,
            // So the screen can say "using your company logo" / "no logo yet".
            'companyLogoUrl' => filled($all['logo_path']) ? Storage::disk('public')->url($all['logo_path']) : null,
            'invoiceLogoUrl' => filled($template['logo_path']) ? Storage::disk('public')->url($template['logo_path']) : null,
            'brandColour' => $all['brand_colour'],
            'hasAbn' => filled($all['abn']),
            'canManage' => $this->canManage(),
        ]);
    }

    public function update(InvoiceTemplateRequest $request): RedirectResponse
    {
        abort_unless($this->canManage(), 403);

        $tenant = app('current_tenant');
        $current = $this->templates->forUi($tenant);

        $this->settings->update($tenant, [
            'invoice_template' => [
                // The uploaded logo is not part of this form — keep whatever
                // the upload endpoint stored.
                ...$this->templates->sanitize($request->validated()),
                'logo_path' => $current['logo_path'],
            ],
            'gst_registered' => $request->boolean('gst_registered'),
        ], 'invoices');

        return back()->with('success', __('common.settings.invoice_template_saved'));
    }

    /**
     * The chosen layout rendered as HTML with SAMPLE data, shown in an iframe
     * beside the form.
     *
     * HTML, not PDF: PDFs are always queued (CLAUDE.md), and dompdf renders
     * this very markup, so what the preview shows is what the PDF prints.
     */
    public function preview(): Response
    {
        $tenant = app('current_tenant');

        $html = view('pdf.invoice', [
            'invoice' => $this->sampleInvoice($tenant),
            'tenant' => $tenant,
            // forScreen: the logo must be an http URL here, not a filesystem
            // path — a browser cannot open the latter.
            'template' => $this->templates->resolve($tenant, forScreen: true),
        ])->render();

        // Its own frame-ancestors: this page is framed by the settings screen.
        return response($html)->withHeaders([
            'Content-Type' => 'text/html; charset=utf-8',
            'Content-Security-Policy' => "frame-ancestors 'self'",
            'X-Robots-Tag' => 'noindex',
        ]);
    }

    /** Replace the invoice-specific logo (falls back to the company logo). */
    public function updateLogo(InvoiceTemplateRequest $request): RedirectResponse
    {
        abort_unless($this->canManage(), 403);

        $tenant = app('current_tenant');
        $template = $this->templates->forUi($tenant);
        $old = $template['logo_path'];

        // PUBLIC disk: dompdf reads it off disk and the settings screen shows
        // it — it is a logo, not a document.
        $path = $request->file('logo')->storeAs(
            "tenants/{$tenant->id}/branding",
            'invoice-logo-'.Str::random(12).'.'.$request->file('logo')->extension(),
            'public',
        );

        $this->settings->update($tenant, [
            'invoice_template' => [...$template, 'logo_path' => $path],
        ], 'invoices');

        if ($old && $old !== $path) {
            Storage::disk('public')->delete($old);
        }

        return back()->with('success', __('common.settings.invoice_template_saved'));
    }

    public function removeLogo(): RedirectResponse
    {
        abort_unless($this->canManage(), 403);

        $tenant = app('current_tenant');
        $template = $this->templates->forUi($tenant);

        if ($template['logo_path']) {
            Storage::disk('public')->delete($template['logo_path']);
        }

        $this->settings->update($tenant, [
            'invoice_template' => [...$template, 'logo_path' => null],
        ], 'invoices');

        return back()->with('success', __('common.settings.invoice_template_saved'));
    }

    /**
     * A realistic invoice that is never saved, built purely to draw the
     * preview. Unsaved models: nothing reaches the database, and the sample can
     * never be mistaken for a real invoice.
     */
    private function sampleInvoice($tenant): Invoice
    {
        $start = Carbon::today()->startOfMonth();

        $invoice = new Invoice([
            'tenant_id' => $tenant->id,
            'type' => Invoice::TYPE_RECURRING,
            'status' => Invoice::STATUS_SENT,
            'billing_period_start' => $start,
            'billing_period_end' => $start->copy()->endOfMonth(),
            'due_date' => Carbon::today()->addDays(7),
            'subtotal' => 154000,
            'total' => 154000,
            'paid_amount' => 50000,
        ]);
        $invoice->id = 1042;
        $invoice->issue_date = Carbon::today();
        $invoice->exists = true;

        $item = new InvoiceItem([
            'description' => 'Weekly rental — Toyota Camry',
            'amount' => 140000,
            'period_start' => $start,
            'period_end' => $start->copy()->addDays(6),
        ]);
        $extra = new InvoiceItem(['description' => 'Airport delivery fee', 'amount' => 14000]);

        // setRelation, so nothing is queried and nothing is written.
        $invoice->setRelation('items', collect([$item, $extra]));
        $invoice->setRelation('payments', collect());
        $invoice->setRelation('customer', new Customer([
            'name' => 'Sample Customer',
            'email' => 'customer@example.com',
        ]));

        return $invoice;
    }

    private function canManage(): bool
    {
        $user = auth('tenant')->user();

        return $user instanceof TenantUser
            && in_array($user->role, [TenantUser::ROLE_ADMIN, TenantUser::ROLE_ACCOUNTS], true);
    }
}
