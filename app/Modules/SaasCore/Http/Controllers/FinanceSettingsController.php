<?php

namespace App\Modules\SaasCore\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\SaasCore\Http\Requests\FinanceSettingsRequest;
use App\Modules\SaasCore\Models\TenantUser;
use App\Modules\SaasCore\Services\TenantSettingsService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Settings → Invoicing & late fees.
 *
 * The late-fee values have been read by ApplyLateFeeAction since the invoice
 * engine was built, but nothing could ever set them — every tenant silently ran
 * on the built-in defaults. This is that screen.
 *
 * Admin or accounts may write; everyone else reads.
 */
class FinanceSettingsController extends Controller
{
    public function __construct(
        private readonly TenantSettingsService $settings,
    ) {}

    public function show(): Response
    {
        $all = $this->settings->all(app('current_tenant'));

        return Inertia::render('Settings/Finance', [
            'settings' => [
                'late_fees_enabled' => (bool) $all['late_fees_enabled'],
                'late_fee_grace_days' => (int) $all['late_fee_grace_days'],
                'late_fee_type' => $all['late_fee_type'],
                'late_fee_amount' => (int) $all['late_fee_amount'],
                'late_fee_percentage' => (int) $all['late_fee_percentage'],
                'invoice_prefix' => $all['invoice_prefix'],
                'invoice_payment_terms_days' => (int) $all['invoice_payment_terms_days'],
                'invoice_footer_note' => $all['invoice_footer_note'],
            ],
            'canManage' => $this->canManage(),
        ]);
    }

    public function update(FinanceSettingsRequest $request): RedirectResponse
    {
        abort_unless($this->canManage(), 403);

        $data = $request->validated();

        $this->settings->update(app('current_tenant'), [
            'late_fees_enabled' => (bool) $data['late_fees_enabled'],
            'late_fee_grace_days' => (int) $data['late_fee_grace_days'],
            'late_fee_type' => $data['late_fee_type'],
            'late_fee_amount' => (int) ($data['late_fee_amount'] ?? 0),
            'late_fee_percentage' => (int) ($data['late_fee_percentage'] ?? 0),
            'invoice_prefix' => $data['invoice_prefix'] ?? null,
            'invoice_payment_terms_days' => (int) $data['invoice_payment_terms_days'],
            'invoice_footer_note' => $data['invoice_footer_note'] ?? null,
        ], 'finance');

        return back()->with('success', __('common.settings.finance_saved'));
    }

    private function canManage(): bool
    {
        $user = auth('tenant')->user();

        return $user instanceof TenantUser
            && in_array($user->role, [TenantUser::ROLE_ADMIN, TenantUser::ROLE_ACCOUNTS], true);
    }
}
