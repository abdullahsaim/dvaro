<?php

namespace App\Modules\SuperAdmin\Http\Requests;

use App\Modules\SaasCore\Models\SubscriptionPayment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates a super admin recording an OFFLINE payment against a tenant's
 * subscription (bank transfer / cash / etc.).
 *
 * amount is entered and validated in CENTS (integer). Authorization
 * ('supportAccess') is enforced in the controller.
 */
class OfflinePaymentRequest extends FormRequest
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
            // Cents — at least 1c.
            'amount' => ['required', 'integer', 'min:1'],
            'method' => ['required', Rule::in(SubscriptionPayment::METHODS)],
            'reference' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'paid_at' => ['required', 'date'],
        ];
    }
}
