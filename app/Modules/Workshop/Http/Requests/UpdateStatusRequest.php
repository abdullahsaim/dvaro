<?php

namespace App\Modules\Workshop\Http\Requests;

use App\Modules\Workshop\Models\ServiceLog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates a service-log status transition. The action re-validates the status
 * against ServiceLog::STATUSES as the authority; this keeps garbage out early.
 */
class UpdateStatusRequest extends FormRequest
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
            'status' => ['required', Rule::in(ServiceLog::STATUSES)],
        ];
    }
}
