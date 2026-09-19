<?php

namespace App\Modules\Finance\Actions;

use App\Actions\BaseAction;
use App\Modules\Finance\DTOs\ExpenseDTO;
use App\Modules\Finance\Events\ExpenseUpdated;
use App\Modules\Finance\Models\Expense;
use App\Modules\Finance\Models\LedgerEntry;
use App\Modules\Finance\Services\ExpenseReceiptStorage;
use App\Modules\Finance\Services\LedgerService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Edits an expense. The ledger is append-only, so a changed TOTAL is recorded
 * as a reversal (−old) followed by a fresh entry (+new); a change that leaves
 * the total alone writes no ledger rows. A new receipt replaces the old one
 * (old file deleted only after commit); $removeReceipt clears it.
 * Voided expenses can't be edited. Fires ExpenseUpdated.
 */
class UpdateExpenseAction extends BaseAction
{
    public function __construct(
        private readonly LedgerService $ledger,
        private readonly ExpenseReceiptStorage $receipts,
    ) {}

    public function execute(
        Expense $expense,
        ExpenseDTO $dto,
        ?UploadedFile $receipt = null,
        bool $removeReceipt = false,
    ): Expense {
        if ($expense->isVoided()) {
            throw ValidationException::withMessages(['expense' => __('common.expenses.voided_locked')]);
        }

        if ($receipt !== null) {
            $this->receipts->assertAllowed($receipt);
        }

        $oldPath = $expense->receipt_path;
        $storedPath = null;

        try {
            DB::transaction(function () use ($expense, $dto, $receipt, $removeReceipt, &$storedPath) {
                $oldTotal = $expense->amount_total;

                $expense->fill($dto->toAttributes())->save();

                if ($expense->amount_total !== $oldTotal) {
                    $this->ledger->appendBusiness((int) $expense->tenant_id, LedgerEntry::TYPE_EXPENSE,
                        -$oldTotal, "Expense amended (reversal): {$expense->description}", 'expense', $expense->id);
                    $this->ledger->appendBusiness((int) $expense->tenant_id, LedgerEntry::TYPE_EXPENSE,
                        $expense->amount_total, "Expense amended: {$expense->description}", 'expense', $expense->id);
                }

                if ($receipt !== null) {
                    $storedPath = $this->receipts->store($expense, $receipt);
                    $expense->forceFill(['receipt_path' => $storedPath])->save();
                } elseif ($removeReceipt) {
                    $expense->forceFill(['receipt_path' => null])->save();
                }
            });
        } catch (Throwable $e) {
            $this->receipts->delete($storedPath);

            throw $e;
        }

        if ($oldPath !== null && $oldPath !== $expense->receipt_path) {
            $this->receipts->delete($oldPath);
        }

        ExpenseUpdated::dispatch($expense);

        return $expense;
    }
}
