<?php

namespace App\Modules\SaasCore\Http\Requests;

use App\Modules\SaasCore\Models\Subscription;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Starting a Stripe checkout for a plan. Authorization (tenant-admin only) is
 * the 'manageSubscription' gate in StripeCheckoutController — same load-bearing
 * forUser pattern as every other tenant action.
 */
class CheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'billing_cycle' => [
                'required',
                Rule::in([Subscription::BILLING_MONTHLY, Subscription::BILLING_ANNUAL]),
            ],
        ];
    }
}
