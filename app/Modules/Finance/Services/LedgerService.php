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
     * @param  int  $tenantId  Owning tenant (set explicitly — the
     *                         caller may not be in the bound tenant
     *                         request context, e.g. system jobs).
     * @param  int  $customerId  Customer the entry belongs to.
     * @param  string  $type  One of LedgerEntry::TYPES.
     * @param  int  $amount  Cents. POSITIVE = charge (owes more),
     *                       NEGATIVE = payment/credit (owes less).
     * @param  string  $description  Human-readable description.
     * @param  string|null  $referenceType  Originating record type, e.g. 'invoice'.
     * @param  int|null  $referenceId  Originating record id.
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
     * Append a BUSINESS-LEVEL entry (no customer) — company expenses only.
     *
     * Sign for these entries: POSITIVE = money spent, NEGATIVE = a reversal
     * (edit / void). They never touch a customer balance (balances are always
     * computed per customer_id), and the DB CHECK constraint
     * ledger_entries_customer_required only admits NULL customers for
     * TYPE_EXPENSE.
     */
    public function appendBusiness(
        int $tenantId,
        string $type,
        int $amount,
        string $description,
        ?string $referenceType = null,
        ?int $referenceId = null,
    ): LedgerEntry {
        if (! in_array($type, LedgerEntry::BUSINESS_TYPES, true)) {
            throw new InvalidArgumentException("Ledger type '{$type}' requires a customer.");
        }

        return LedgerEntry::create([
            'tenant_id' => $tenantId,
            'customer_id' => null,
            'type' => $type,
            'amount' => $amount,
            'description' => $description,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
        ]);
    }

    /**
     * Bond ledger entries are a SEPARATE pot of money (a deposit held in
     * trust against damage, not rental debt) and must never be mixed into
     * "what does the customer owe for the rental" — see getBalance().
     */
    private const BOND_TYPES = [
        LedgerEntry::TYPE_BOND_COLLECTION,
        LedgerEntry::TYPE_BOND_DEDUCTION,
        LedgerEntry::TYPE_BOND_REFUND,
    ];

    /**
     * Current balance for a customer, in cents.
     *
     * Net of every RENTAL entry: POSITIVE means the customer owes the tenant,
     * NEGATIVE means the customer is in credit. Tenant-scoped automatically
     * via HasTenant.
     *
     * Bond entries are DELIBERATELY excluded. A bond collected is not rental
     * revenue and must not make this balance look like the customer owes
     * more; a bond refunded is not a rental credit either. Mixing them in
     * would silently distort every customer balance shown across the app
     * (the customer page, the dashboard's receivables, invoice context) the
     * moment a bond entry exists. Use bondHeld() for the bond's own figure.
     */
    public function getBalance(int $customerId): int
    {
        return (int) LedgerEntry::forCustomer($customerId)
            ->whereNotIn('type', self::BOND_TYPES)
            ->sum('amount');
    }

    /**
     * What remains of ONE agreement's bond, in cents — collection minus
     * whatever has since been deducted or refunded against it. Scoped to a
     * single agreement (not the whole customer): a customer can have several
     * agreements, each with its own bond, over time.
     *
     * Sign convention for bond entries specifically (distinct from the rental
     * balance above): collection is POSITIVE (money now held), deduction and
     * refund are both NEGATIVE (reduce what is still held). A fully refunded
     * bond nets to exactly 0.
     */
    public function bondHeld(int $agreementId): int
    {
        return (int) LedgerEntry::query()
            ->where('reference_type', 'agreement')
            ->where('reference_id', $agreementId)
            ->whereIn('type', self::BOND_TYPES)
            ->sum('amount');
    }
}
