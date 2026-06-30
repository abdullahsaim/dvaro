<?php

namespace App\Modules\Notification\Services;

use App\Modules\SaasCore\Models\Tenant;
use App\Services\BaseService;

/**
 * Builds and dispatches a portal password-reset email through the existing
 * NotificationService (reuse, not rebuild). The reset link is supplied ready
 * built (with the tenant slug embedded in its path) by the calling model's
 * ResetsPasswordWithinTenant trait.
 *
 * NotificationService never throws and logs exactly one NotificationLog row, so
 * a provider outage degrades to a logged failure rather than breaking the reset
 * request. In dev the Log email provider records the email (and the link).
 */
class PortalPasswordResetNotifier extends BaseService
{
    public function __construct(
        private readonly NotificationService $notifications,
    ) {}

    public function send(
        Tenant $tenant,
        string $email,
        string $resetUrl,
        string $notifiableType,
        int $notifiableId,
    ): void {
        $subject = 'Reset your '.$tenant->name.' password';

        $body = $this->body($tenant, $resetUrl);

        $this->notifications->sendEmail(
            tenant: $tenant,
            to: $email,
            subject: $subject,
            body: $body,
            eventType: 'password_reset',
            notifiableId: $notifiableId,
            notifiableType: $notifiableType,
        );
    }

    /**
     * Minimal, email-client-safe HTML (inline styles only). Notification body
     * copy is English-only per CLAUDE.md (body i18n is deferred).
     */
    private function body(Tenant $tenant, string $resetUrl): string
    {
        $safeUrl = e($resetUrl);
        $safeName = e($tenant->name);

        return <<<HTML
            <div style="font-family: Arial, sans-serif; color: #0f172a; line-height: 1.5;">
                <p>Hello,</p>
                <p>We received a request to reset your password for your {$safeName} account.</p>
                <p>
                    <a href="{$safeUrl}" style="display: inline-block; padding: 10px 16px; background: #1e293b; color: #ffffff; text-decoration: none; border-radius: 6px;">
                        Reset password
                    </a>
                </p>
                <p>This link will expire in 60 minutes. If you did not request a password reset, no action is needed.</p>
                <p style="color: #64748b; font-size: 12px;">If the button does not work, copy and paste this link into your browser:<br>{$safeUrl}</p>
            </div>
            HTML;
    }
}
