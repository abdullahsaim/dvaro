<?php

namespace App\Modules\Invoice\Http\Requests;

use App\Modules\Invoice\Models\Invoice;
use App\Modules\Invoice\Models\Payment;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates a manual payment. Authorization is handled by InvoicePolicy in the
 * controller (against the tenant guard) — see InvoiceController.
 *
 * amount is integer CENTS (min 1). A cancelled invoice cannot be paid; that is
 * guarded here for a friendly Inertia error, and RecordPaymentAction re-guards as
 * the authority.
 */
class RecordPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'amount' => ['required', 'integer', 'min:1'],
            'method' => ['required', Rule::in(Payment::METHODS)],
            'notes' => ['nullable', 'string', 'max:1000'],
            'paid_at' => ['nullable', 'date'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $invoice = $this->route('invoice');

            if ($invoice instanceof Invoice && $invoice->status === Invoice::STATUS_CANCELLED) {
                $validator->errors()->add('amount', __('invoice.cancelled_not_payable'));
            }
        });
    }
}
