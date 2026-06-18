<?php

namespace App\Modules\Finance\Services;

use App\Modules\Finance\Models\LedgerEntry;
use App\Services\BaseService;
use InvalidArgumentException;

/**
 * The single sanctioned gateway for writing to and reading balances from the
 * append-only ledger (CLAUDE.md: "LEDGER = SYSTEM OF RECORD").
 *
 * Never create LedgerEntry rows directly anywhere else — always go through
 * append() so the type is validated and the write path stays in one place.
 */
class LedgerService extends BaseService
{
    /**
     * Append a single immutable entry to the ledger.
     *
     * @param  int          $tenantId       Owning tenant (set explicitly — the
     *                                       caller may not be in the bound tenant
     *                                       request context, e.g. system jobs).
     * @param  int          $customerId     Customer the entry belongs to.
     * @param  string       $type           One of LedgerEntry::TYPES.
     * @param  int          $amount          Cents. POSITIVE = charge (owes more),
     *                                        NEGATIVE = payment/credit (owes less).
     * @param  string       $description    Human-readable description.
     * @param  string|null  $referenceType  Originating record type, e.g. 'invoice'.
     * @param  int|null     $referenceId    Originating record id.
     */
    public function append(
        int $tenantId,
        int $customerId,
        string $type,
        int $amount,
        string $description,
        ?string $referenceType = null,
        ?int $referenceId = null,
    ): LedgerEntry {
        if (! in_array($type, LedgerEntry::TYPES, true)) {
            throw new InvalidArgumentException("Unknown ledger entry type: '{$type}'.");
        }

        // created_by is left null (system-generated) for now — it is wired to
        // the acting user once controllers/auth exist in a later session.
        return LedgerEntry::create([
            'tenant_id' => $tenantId,
            'customer_id' => $customerId,
            'type' => $type,
            'amount' => $amount,
            'description' => $description,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
        ]);
    }

    /**
     * Current balance for a customer, in cents.
     *
     * Net of every entry: POSITIVE means the customer owes the tenant, NEGATIVE
     * means the customer is in credit. Tenant-scoped automatically via HasTenant.
     */
    public function getBalance(int $customerId): int
    {
        return (int) LedgerEntry::forCustomer($customerId)->sum('amount');
    }
}
