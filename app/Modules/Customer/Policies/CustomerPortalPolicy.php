<?php

namespace App\Modules\Customer\Policies;

use App\Modules\Agreement\Models\Agreement;
use App\Modules\Customer\Models\CustomerUser;
use App\Modules\Invoice\Models\Invoice;

/**
 * Authorization for Customer Portal resources, against the 'customer' guard.
 *
 * Every ability scopes to the authenticated customer's OWN customer_id — a
 * logged-in customer may only ever see their own invoices and agreements, never
 * another customer's, even within the same tenant. Tenant isolation is already
 * enforced upstream (CustomerUser is tenant-scoped; {invoice}/{agreement} route
 * binding resolves through TenantScope → cross-tenant 404); the tenant_id check
 * here is defense-in-depth, and customer_id is the real per-customer gate.
 *
 * Registered as Gate::define delegations in AppServiceProvider (NOT Gate::policy
 * — Invoice and Agreement already map to their own policies, and a model can
 * only have one). Controllers call
 * Gate::forUser(auth('customer')->user())->authorize('<ability>', $model) — the
 * same load-bearing forUser pattern used across every module.
 */
class CustomerPortalPolicy
{
    public function viewInvoice(CustomerUser $user, Invoice $invoice): bool
    {
        return $this->ownsInvoice($user, $invoice);
    }

    public function viewAgreement(CustomerUser $user, Agreement $agreement): bool
    {
        return (int) $agreement->customer_id === (int) $user->customer_id
            && (int) $agreement->tenant_id === (int) $user->tenant_id;
    }

    /**
     * A customer may pay an invoice only if it is theirs AND not cancelled.
     */
    public function makePayment(CustomerUser $user, Invoice $invoice): bool
    {
        return $this->ownsInvoice($user, $invoice)
            && $invoice->status !== Invoice::STATUS_CANCELLED;
    }

    private function ownsInvoice(CustomerUser $user, Invoice $invoice): bool
    {
        return (int) $invoice->customer_id === (int) $user->customer_id
            && (int) $invoice->tenant_id === (int) $user->tenant_id;
    }
}
