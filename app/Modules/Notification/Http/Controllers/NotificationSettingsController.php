<?php

namespace App\Modules\Notification\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Notification\Http\Requests\UpdateNotificationSettingsRequest;
use App\Modules\Notification\Services\NotificationMatrix;
use App\Modules\SaasCore\Models\TenantUser;
use App\Modules\SaasCore\Services\TenantSettingsService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Settings → Notifications: the master channel switches, the fleet reminder
 * lead times, and the per-trigger matrix (who hears about what, on which
 * channel).
 *
 * Providers (Mailgun / ClickSend / …) moved to Settings → Integrations; this
 * page decides WHAT is sent, that one decides HOW it leaves the building.
 *
 * Thin controller: authorize → read/write through TenantSettingsService (which
 * audits every change) → render/redirect.
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
    public function __construct(
        private readonly TenantSettingsService $settings,
        private readonly NotificationMatrix $matrix,
    ) {}

    public function edit(): Response
    {
        $this->authorizeAdmin();

        $tenant = app('current_tenant');
        $settings = $this->settings->all($tenant);

        return Inertia::render('Notification/Settings', [
            'settings' => [
                'notify_email_enabled' => (bool) $settings['notify_email_enabled'],
                'notify_sms_enabled' => (bool) $settings['notify_sms_enabled'],
                'notify_whatsapp_enabled' => (bool) $settings['notify_whatsapp_enabled'],
                'fleet_reminders_enabled' => (bool) $settings['fleet_reminders_enabled'],
                'fleet_reminder_days' => (int) $settings['fleet_reminder_days'],
                'fleet_reminder_km' => (int) $settings['fleet_reminder_km'],
            ],
            'matrix' => $this->matrix->forUi($tenant),
            'channels' => [
                NotificationMatrix::EMAIL,
                NotificationMatrix::SMS,
                NotificationMatrix::WHATSAPP,
            ],
        ]);
    }

    public function update(UpdateNotificationSettingsRequest $request): RedirectResponse
    {
        $this->authorizeAdmin();

        $tenant = app('current_tenant');

        $this->settings->update($tenant, [
            'notify_email_enabled' => $request->boolean('notify_email_enabled'),
            'notify_sms_enabled' => $request->boolean('notify_sms_enabled'),
            'notify_whatsapp_enabled' => $request->boolean('notify_whatsapp_enabled'),
            'fleet_reminders_enabled' => $request->boolean('fleet_reminders_enabled'),
            'fleet_reminder_days' => $request->integer('fleet_reminder_days'),
            'fleet_reminder_km' => $request->integer('fleet_reminder_km'),
            // Unknown triggers, unsupported channels and locked rows are
            // dropped here — the form can never widen what it controls.
            'notification_matrix' => $this->matrix->sanitize((array) $request->input('matrix', [])),
        ], 'notifications');

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
