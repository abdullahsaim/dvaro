<?php

namespace App\Modules\Finance\DTOs;

use App\DTOs\BaseDTO;
use Illuminate\Http\Request;

/**
 * Validated expense input → Record/UpdateExpenseAction. Money in CENTS.
 *
 * GST (Australia): amounts are entered GST-INCLUSIVE, as printed on receipts.
 * includes_gst=true  → GST = the override if given (mixed receipts), else
 *                      1/11 of the total, rounded to the nearest cent.
 * includes_gst=false → GST = 0 (e.g. most government fees).
 */
class ExpenseDTO extends BaseDTO
{
    public function __construct(
        public readonly int $expense_category_id,
        public readonly string $expense_date,
        public readonly string $description,
        public readonly int $amount_total,
        public readonly bool $includes_gst,
        public readonly string $payment_method,
        public readonly ?int $gst_override = null,
        public readonly ?int $vehicle_id = null,
        public readonly ?string $supplier = null,
        public readonly ?string $notes = null,
    ) {}

    public static function fromRequest(Request $request): self
    {
        return new self(
            expense_category_id: $request->integer('expense_category_id'),
            expense_date: $request->string('expense_date')->toString(),
            description: trim($request->string('description')->toString()),
            amount_total: $request->integer('amount_total'),
            includes_gst: $request->boolean('includes_gst'),
            payment_method: $request->string('payment_method')->toString(),
            gst_override: $request->filled('gst_amount') ? $request->integer('gst_amount') : null,
            vehicle_id: $request->filled('vehicle_id') ? $request->integer('vehicle_id') : null,
            supplier: $request->filled('supplier') ? trim($request->string('supplier')->toString()) : null,
            notes: $request->filled('notes') ? $request->string('notes')->toString() : null,
        );
    }

    /** GST component in cents (never more than the total). */
    public function gst(): int
    {
        if (! $this->includes_gst) {
            return 0;
        }

        $gst = $this->gst_override ?? self::standardGst($this->amount_total);

        return max(0, min($gst, $this->amount_total));
    }

    /** 1/11 of a GST-inclusive amount, to the nearest cent. */
    public static function standardGst(int $totalCents): int
    {
        return (int) round($totalCents / 11);
    }

    /**
     * @return array<string, mixed>
     */
    public function toAttributes(): array
    {
        $gst = $this->gst();

        return [
            'expense_category_id' => $this->expense_category_id,
            'vehicle_id' => $this->vehicle_id,
            'expense_date' => $this->expense_date,
            'description' => $this->description,
            'supplier' => $this->supplier,
            'amount_total' => $this->amount_total,
            'gst_amount' => $gst,
            'amount_ex_gst' => $this->amount_total - $gst,
            'includes_gst' => $this->includes_gst,
            'payment_method' => $this->payment_method,
            'notes' => $this->notes,
        ];
    }
}
