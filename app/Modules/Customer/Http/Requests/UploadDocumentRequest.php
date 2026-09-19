<?php

namespace App\Modules\Customer\Http\Requests;

use App\Modules\Customer\Actions\UploadCustomerDocumentAction;
use App\Modules\Customer\Models\Customer;
use App\Modules\SaasCore\Services\PlanEnforcementService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates a customer identity-document upload. Authorization is handled by
 * CustomerPolicy in the controller (against the tenant guard).
 *
 * `type` arrives as a route segment and is merged in so it is validated like
 * any other input. `max` is the LOWER of the 10MB ceiling and the tenant plan's
 * max_file_size_mb, so an oversize file surfaces as an inline field error; the
 * action re-checks the plan limit as a hard block regardless.
 */
class UploadDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['type' => $this->route('type')]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $maxKb = UploadCustomerDocumentAction::MAX_KB;
        $planMb = app(PlanEnforcementService::class)->fileSizeLimitMb();

        if ($planMb !== null) {
            $maxKb = min($maxKb, $planMb * 1024);
        }

        return [
            'file' => ['required', 'file', 'mimes:'.implode(',', UploadCustomerDocumentAction::EXTENSIONS), 'max:'.$maxKb],
            'type' => ['required', Rule::in(array_keys(Customer::DOCUMENT_TYPES))],
        ];
    }
}
