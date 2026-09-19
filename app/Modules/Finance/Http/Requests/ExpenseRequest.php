<?php

namespace App\Modules\Finance\Http\Requests;

use App\Modules\Finance\Models\Expense;
use App\Modules\Finance\Services\ExpenseReceiptStorage;
use App\Modules\SaasCore\Services\PlanEnforcementService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Create / edit an expense. Money arrives in CENTS (the form converts AUD).
 * Category and vehicle must belong to the CURRENT tenant — the exists rules run
 * raw SQL that bypasses TenantScope, so tenant_id is constrained explicitly.
 * Receipt max = lower of 10MB and the plan's max_file_size_mb (the action
 * re-checks the plan limit as a hard block).
 * Authorization: ExpensePolicy in the controller (tenant guard).
 */
class ExpenseRequest extends FormRequest
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
        $tenantId = app('current_tenant')->id;

        $maxKb = ExpenseReceiptStorage::MAX_KB;
        $planMb = app(PlanEnforcementService::class)->fileSizeLimitMb();
        if ($planMb !== null) {
            $maxKb = min($maxKb, $planMb * 1024);
        }

        return [
            'expense_category_id' => ['required', 'integer',
                Rule::exists('expense_categories', 'id')->where('tenant_id', $tenantId)],
            'vehicle_id' => ['nullable', 'integer',
                Rule::exists('vehicles', 'id')->where('tenant_id', $tenantId)],
            'expense_date' => ['required', 'date', 'before_or_equal:today'],
            'description' => ['required', 'string', 'max:255'],
            'supplier' => ['nullable', 'string', 'max:255'],
            'amount_total' => ['required', 'integer', 'min:1', 'max:100000000'],
            'includes_gst' => ['required', 'boolean'],
            'gst_amount' => ['nullable', 'integer', 'min:0', 'lte:amount_total'],
            'payment_method' => ['required', Rule::in(Expense::PAYMENT_METHODS)],
            'notes' => ['nullable', 'string', 'max:2000'],
            'receipt' => ['nullable', 'file', 'mimes:'.implode(',', ExpenseReceiptStorage::EXTENSIONS), 'max:'.$maxKb],
            'remove_receipt' => ['nullable', 'boolean'],
        ];
    }
}
