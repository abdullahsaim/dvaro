<?php

namespace App\Modules\Finance\Actions;

use App\Actions\BaseAction;
use App\Modules\Finance\Events\ExpenseVoided;
use App\Modules\Finance\Models\Expense;
use App\Modules\Finance\Models\LedgerEntry;
use App\Modules\Finance\Services\LedgerService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Voids an expense — there are NO hard deletes. Stamps voided_at/by/reason and
 * appends a reversing ledger entry (−amount_total). The row (and its receipt)
 * stay for the audit trail; voided expenses drop out of every total.
 * Fires ExpenseVoided.
 */
class VoidExpenseAction extends BaseAction
{
    public function __construct(
        private readonly LedgerService $ledger,
    ) {}

    public function execute(Expense $expense, string $reason, ?int $actorId = null): Expense
    {
        if ($expense->isVoided()) {
            throw ValidationException::withMessages(['expense' => __('common.expenses.already_voided')]);
        }

        DB::transaction(function () use ($expense, $reason, $actorId) {
            $expense->forceFill([
                'voided_at' => now(),
                'voided_by' => $actorId,
                'void_reason' => $reason,
            ])->save();

            $this->ledger->appendBusiness((int) $expense->tenant_id, LedgerEntry::TYPE_EXPENSE,
                -$expense->amount_total, "Expense voided: {$expense->description}", 'expense', $expense->id);
        });

        ExpenseVoided::dispatch($expense);

        return $expense;
    }
}
