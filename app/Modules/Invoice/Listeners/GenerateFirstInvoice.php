<?php

namespace App\Modules\Invoice\Listeners;

use App\Modules\Agreement\Events\AgreementSigned;
use App\Modules\Agreement\Models\Agreement;
use App\Modules\Invoice\Services\RecurringInvoiceService;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Generates the FIRST invoice for an agreement the moment it is signed
 * (CLAUDE.md: "Auto ... invoice creation on agreement sign").
 *
 * Deliberately SYNCHRONOUS (not ShouldQueue): the financial record should
 * exist by the time the admin is redirected — it is a local DB write, not an
 * external call. It runs inside AgreementService::sign()'s transaction with the
 * tenant already bound (web request), so RecurringInvoiceService resolves scope
 * correctly.
 *
 * GUARD: only version 1 (no parent) bills. A re-signed version (v2+, produced
 * by a mid-cycle vehicle change) must NOT raise another full invoice — the
 * vehicle-change flow already raised its prorated invoices.
 *
 * RESILIENT: a failed first invoice is logged but never rethrown, so it cannot
 * roll back the signature (the legally important artifact). Ops can regenerate
 * from the log if needed.
 */
class GenerateFirstInvoice
{
    public function __construct(
        private readonly RecurringInvoiceService $invoices,
    ) {}

    public function handle(AgreementSigned $event): void
    {
        $agreement = $event->agreement;

        if ((int) $agreement->version !== 1 || $agreement->parent_agreement_id !== null) {
            return;
        }

        // Backstop guard against signing a status other than draft→signed
        // double-firing: only bill signed agreements.
        if ($agreement->status !== Agreement::STATUS_SIGNED) {
            return;
        }

        try {
            $this->invoices->generateForAgreement($agreement);
        } catch (Throwable $e) {
            Log::error('GenerateFirstInvoice failed', [
                'tenant_id' => $agreement->tenant_id,
                'agreement_id' => $agreement->id,
                'message' => $e->getMessage(),
            ]);
        }
    }
}
