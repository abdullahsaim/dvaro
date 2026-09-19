<?php

namespace App\Modules\Finance\Actions;

use App\Actions\BaseAction;
use App\Modules\Finance\DTOs\ExpenseDTO;
use App\Modules\Finance\Events\ExpenseRecorded;
use App\Modules\Finance\Models\Expense;
use App\Modules\Finance\Models\LedgerEntry;
use App\Modules\Finance\Services\ExpenseReceiptStorage;
use App\Modules\Finance\Services\LedgerService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Records a company expense — the ONLY creation path. One transaction:
 * expense row + business-level ledger entry (+amount_total, TYPE_EXPENSE,
 * no customer) + optional receipt. A stored receipt is removed again if the
 * transaction fails, so nothing is orphaned. Fires ExpenseRecorded.
 */
class RecordExpenseAction extends BaseAction
{
    public function __construct(
        private readonly LedgerService $ledger,
        private readonly ExpenseReceiptStorage $receipts,
    ) {}

    public function execute(ExpenseDTO $dto, ?UploadedFile $receipt = null, ?int $actorId = null): Expense
    {
        if ($receipt !== null) {
            $this->receipts->assertAllowed($receipt); // hard plan limit, before any write
        }

        $storedPath = null;

        try {
            $expense = DB::transaction(function () use ($dto, $receipt, $actorId, &$storedPath) {
                $expense = Expense::create([...$dto->toAttributes(), 'created_by' => $actorId]);

                $this->ledger->appendBusiness(
                    (int) $expense->tenant_id,
                    LedgerEntry::TYPE_EXPENSE,
                    $expense->amount_total,
                    "Expense: {$expense->description}",
                    'expense',
                    $expense->id,
                );

                if ($receipt !== null) {
                    $storedPath = $this->receipts->store($expense, $receipt);
                    $expense->forceFill(['receipt_path' => $storedPath])->save();
                }

                return $expense;
            });
        } catch (Throwable $e) {
            $this->receipts->delete($storedPath);

            throw $e;
        }

        ExpenseRecorded::dispatch($expense);

        return $expense;
    }
}
