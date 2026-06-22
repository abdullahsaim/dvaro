<?php

namespace App\Modules\Invoice\Policies;

use App\Modules\Invoice\Models\Invoice;
use App\Modules\SaasCore\Models\TenantUser;

/**
 * Authorization for Invoice actions.
 *
 * Tenant isolation is PRIMARILY enforced by TenantScope: route-model binding for
 * {invoice} resolves through the global scope, so an invoice belonging to another
 * tenant is never found (404) before a policy runs. This policy is explicit
 * defense-in-depth — it asserts the acting tenant user and the target invoice
 * belong to the same tenant.
 *
 * IMPORTANT: these checks must be evaluated against the TENANT guard user. The
 * controller calls Gate::forUser(auth('tenant')->user())->authorize(...) for
 * exactly this reason — see InvoiceController.
 */
class InvoicePolicy
{
    public function viewAny(TenantUser $user): bool
    {
        return true;
    }

    public function view(TenantUser $user, Invoice $invoice): bool
    {
        return $this->sameTenant($user, $invoice);
    }

    /** Recording a payment against an invoice. */
    public function recordPayment(TenantUser $user, Invoice $invoice): bool
    {
        return $this->sameTenant($user, $invoice);
    }

    /** Manually flagging an invoice overdue. */
    public function markOverdue(TenantUser $user, Invoice $invoice): bool
    {
        return $this->sameTenant($user, $invoice);
    }

    private function sameTenant(TenantUser $user, Invoice $invoice): bool
    {
        return (int) $user->tenant_id === (int) $invoice->tenant_id;
    }
}
