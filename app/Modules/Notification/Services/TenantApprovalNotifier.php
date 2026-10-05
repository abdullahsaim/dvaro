<?php

namespace App\Modules\Notification\Services;

use App\Modules\SaasCore\Models\Tenant;
use App\Modules\SaasCore\Models\TenantUser;
use App\Scopes\TenantScope;
use App\Services\BaseService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;

/**
 * Tells a tenant's admin the outcome of manual registration approval. Sent
 * DIRECTLY (not through the per-trigger notification matrix) — like
 * TenantEmailVerificationNotifier, this is an account-access email, not a
 * business notification a tenant should be able to switch off (and a
 * rejected/not-yet-approved tenant has no notification preferences to ask).
 */
class TenantApprovalNotifier extends BaseService
{
    public function __construct(
        private readonly NotificationService $notifications,
    ) {}

    public function sendApproved(Tenant $tenant): void
    {
        $admin = $this->admin($tenant);

        if ($admin === null) {
            return;
        }

        $loginUrl = URL::route('tenant.login', ['tenant_slug' => $tenant->slug]);
        $safeName = e($tenant->name);
        $safeUrl = e($loginUrl);

        $body = <<<HTML
            <div style="font-family: Arial, sans-serif; color: #0f172a; line-height: 1.5;">
                <p>Hello,</p>
                <p>Good news — {$safeName}'s DVARO account has been approved and is ready to use.</p>
                <p>
                    <a href="{$safeUrl}" style="display: inline-block; padding: 10px 16px; background: #1e293b; color: #ffffff; text-decoration: none; border-radius: 6px;">
                        Sign in
                    </a>
                </p>
            </div>
            HTML;

        $this->notifications->sendEmail(
            tenant: $tenant,
            to: $admin->email,
            subject: 'Your DVARO account is approved',
            body: $body,
            eventType: 'tenant_approved',
            notifiableId: $admin->id,
            notifiableType: 'tenant_user',
        );
    }

    public function sendRejected(Tenant $tenant): void
    {
        $admin = $this->admin($tenant);

        if ($admin === null) {
            return;
        }

        $safeName = e($tenant->name);

        $body = <<<HTML
            <div style="font-family: Arial, sans-serif; color: #0f172a; line-height: 1.5;">
                <p>Hello,</p>
                <p>We were unable to approve the DVARO registration for {$safeName} at this time.</p>
                <p>If you believe this is a mistake, please get in touch with our team.</p>
            </div>
            HTML;

        $this->notifications->sendEmail(
            tenant: $tenant,
            to: $admin->email,
            subject: 'Your DVARO registration was not approved',
            body: $body,
            eventType: 'tenant_rejected',
            notifiableId: $admin->id,
            notifiableType: 'tenant_user',
        );
    }

    private function admin(Tenant $tenant): ?TenantUser
    {
        $admin = TenantUser::withoutGlobalScope(TenantScope::class)
            ->where('tenant_id', $tenant->id)
            ->where('role', TenantUser::ROLE_ADMIN)
            ->first();

        if ($admin === null) {
            Log::warning(self::class.': no admin recipient', ['tenant_id' => $tenant->id]);
        }

        return $admin;
    }
}
