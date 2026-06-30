<?php

namespace App\Traits;

use App\Modules\Notification\Services\PortalPasswordResetNotifier;

/**
 * Tenant-scoped password reset for a portal Authenticatable (TenantUser,
 * CustomerUser, Mechanic).
 *
 * Two overrides of the framework's CanResetPassword behaviour:
 *
 *  1. getEmailForPasswordReset() returns a COMPOSITE key — "{guard}:{tenant_id}:
 *     {email}" — which is what the password broker stores/looks tokens up by.
 *     The broker still FINDS the user by the real email (+ TenantScope on the
 *     bound tenant), but the token row is keyed by the composite, so a token
 *     issued for tenant A's address can never be redeemed under tenant B even
 *     when both tenants share that literal address. This is the core of the
 *     "same email across tenants only resets that tenant's account" guarantee.
 *
 *  2. sendPasswordResetNotification() routes the email through the existing
 *     NotificationService (PortalPasswordResetNotifier), building a reset link
 *     whose PATH embeds the tenant slug (e.g. /app/{slug}/reset-password/{token})
 *     — the link is clicked cold from an inbox with no request context, so the
 *     slug must travel in the URL for the tenant-scoped broker to have something
 *     to scope against on the way back.
 *
 * The using model must define the two small hooks below.
 */
trait ResetsPasswordWithinTenant
{
    /** A stable per-guard key prefix: 'tenant' | 'customer' | 'mechanic'. */
    abstract public function passwordResetGuardKey(): string;

    /** The named route for the reset form (carries {tenant_slug} + {token}). */
    abstract public function passwordResetRouteName(): string;

    public function getEmailForPasswordReset(): string
    {
        return $this->passwordResetGuardKey().':'.$this->tenant_id.':'.$this->email;
    }

    public function sendPasswordResetNotification($token): void
    {
        $url = route($this->passwordResetRouteName(), [
            'tenant_slug' => $this->tenant->slug,
            'token' => $token,
            // Real email travels as a query param so the reset form can resubmit
            // it for the (tenant-scoped) user lookup. Never the composite key.
            'email' => $this->email,
        ]);

        app(PortalPasswordResetNotifier::class)->send(
            tenant: $this->tenant,
            email: $this->email,
            resetUrl: $url,
            notifiableType: $this->passwordResetGuardKey(),
            notifiableId: $this->id,
        );
    }
}
