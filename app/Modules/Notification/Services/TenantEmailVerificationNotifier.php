<?php

namespace App\Modules\Notification\Services;

use App\Modules\SaasCore\Models\Tenant;
use App\Modules\SaasCore\Models\TenantUser;
use App\Services\BaseService;
use Illuminate\Support\Facades\URL;

/**
 * Builds and sends a tenant admin's email-verification link, mirroring
 * PortalPasswordResetNotifier's pattern exactly: a signed, expiring URL built
 * here (no stored token — the HMAC signature IS the credential), routed
 * through the existing NotificationService so a provider outage degrades to a
 * logged failure rather than breaking signup. Sent DIRECTLY (not through the
 * per-trigger notification matrix) — verification is a core account-security
 * action, not a business notification a tenant should be able to switch off.
 */
class TenantEmailVerificationNotifier extends BaseService
{
    /** Minutes the signed link stays valid. A resend simply issues a new one. */
    private const EXPIRY_MINUTES = 60;

    public function __construct(
        private readonly NotificationService $notifications,
    ) {}

    public function send(TenantUser $user): void
    {
        $tenant = $user->tenant;
        $url = $this->buildUrl($tenant, $user);

        $subject = 'Verify your email for '.$tenant->name;
        $body = $this->body($tenant, $url);

        $this->notifications->sendEmail(
            tenant: $tenant,
            to: $user->email,
            subject: $subject,
            body: $body,
            eventType: 'email_verification',
            notifiableId: $user->id,
            notifiableType: 'tenant_user',
        );
    }

    public function buildUrl(Tenant $tenant, TenantUser $user): string
    {
        return URL::temporarySignedRoute('tenant.verification.verify', now()->addMinutes(self::EXPIRY_MINUTES), [
            'tenant_slug' => $tenant->slug,
            'id' => $user->id,
            'hash' => sha1($user->email),
        ]);
    }

    /**
     * Minimal, email-client-safe HTML (inline styles only). Notification body
     * copy is English-only per CLAUDE.md (body i18n is deferred).
     */
    private function body(Tenant $tenant, string $url): string
    {
        $safeUrl = e($url);
        $safeName = e($tenant->name);

        return <<<HTML
            <div style="font-family: Arial, sans-serif; color: #0f172a; line-height: 1.5;">
                <p>Hello,</p>
                <p>Please confirm your email address to finish setting up your {$safeName} account on DVARO.</p>
                <p>
                    <a href="{$safeUrl}" style="display: inline-block; padding: 10px 16px; background: #1e293b; color: #ffffff; text-decoration: none; border-radius: 6px;">
                        Verify email
                    </a>
                </p>
                <p>This link will expire in 60 minutes. You can request a new one anytime from your dashboard.</p>
                <p style="color: #64748b; font-size: 12px;">If the button does not work, copy and paste this link into your browser:<br>{$safeUrl}</p>
            </div>
            HTML;
    }
}
