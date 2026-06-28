<?php

namespace App\Modules\Notification\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Notification\Http\Requests\UpdateNotificationSettingsRequest;
use App\Modules\SaasCore\Models\TenantUser;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Tenant notification settings — provider selection + per-channel toggles.
 * Thin controller: authorize → read/write tenant settings → render/redirect.
 *
 * ──────────────────────────────────────────────────────────────────────────
 * AUTHORIZATION: notification settings are TENANT-WIDE configuration, not a
 * per-record model, so there is no policy/Gate target. Access is restricted to
 * tenant_admin directly against the TENANT guard user (auth('tenant')). Staff
 * and accounts roles cannot change how the company communicates with customers.
 * ──────────────────────────────────────────────────────────────────────────
 */
class NotificationSettingsController extends Controller
{
    public function edit(): Response
    {
        $this->authorizeAdmin();

        $tenant = app('current_tenant');
        $settings = $tenant->settings ?? [];

        return Inertia::render('Notification/Settings', [
            'settings' => [
                'email_provider' => $settings['email_provider'] ?? 'log',
                'sms_provider' => $settings['sms_provider'] ?? 'log',
                'notify_email_enabled' => (bool) ($settings['notify_email_enabled'] ?? true),
                'notify_sms_enabled' => (bool) ($settings['notify_sms_enabled'] ?? false),
                'notify_whatsapp_enabled' => (bool) ($settings['notify_whatsapp_enabled'] ?? false),
            ],
            'emailProviders' => UpdateNotificationSettingsRequest::EMAIL_PROVIDERS,
            'smsProviders' => UpdateNotificationSettingsRequest::SMS_PROVIDERS,
        ]);
    }

    public function update(UpdateNotificationSettingsRequest $request): RedirectResponse
    {
        $this->authorizeAdmin();

        $tenant = app('current_tenant');

        // Merge into existing settings so unrelated keys (plan/limits/etc.) are
        // preserved. settings is an array cast on the Tenant model.
        $tenant->settings = [
            ...($tenant->settings ?? []),
            'email_provider' => $request->string('email_provider')->toString(),
            'sms_provider' => $request->string('sms_provider')->toString(),
            'notify_email_enabled' => $request->boolean('notify_email_enabled'),
            'notify_sms_enabled' => $request->boolean('notify_sms_enabled'),
            'notify_whatsapp_enabled' => $request->boolean('notify_whatsapp_enabled'),
        ];
        $tenant->save();

        return redirect()
            ->route('tenant.notifications.settings', ['tenant_slug' => $tenant->slug])
            ->with('success', __('common.notifications.saved'));
    }

    /**
     * Only the tenant_admin may manage notification settings. Checked against
     * the tenant guard (never the empty default web guard).
     */
    private function authorizeAdmin(): void
    {
        $user = auth('tenant')->user();

        abort_unless($user instanceof TenantUser && $user->role === TenantUser::ROLE_ADMIN, 403);
    }
}
