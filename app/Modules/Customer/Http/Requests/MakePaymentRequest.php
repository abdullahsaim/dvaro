<?php

namespace App\Modules\Customer\Http\Requests;

use App\Modules\Invoice\Models\Payment;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates a customer-initiated manual payment from the portal.
 *
 * Only the two manual methods are offered here (cash / bank_transfer) — online
 * gateway payments (Stripe/PayPal) arrive in a later session. amount is integer
 * CENTS. The invoice's payability (belongs to this customer + not cancelled) is
 * enforced by CustomerPortalPolicy::makePayment, with RecordPaymentAction as the
 * authoritative backstop.
 */
class MakePaymentRequest extends FormRequest
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
            'method' => ['required', 'string', 'in:'.Payment::METHOD_CASH.','.Payment::METHOD_BANK_TRANSFER],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
