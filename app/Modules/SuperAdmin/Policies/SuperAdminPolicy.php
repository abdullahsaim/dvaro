<?php

namespace App\Modules\SuperAdmin\Policies;

use App\Modules\SuperAdmin\Models\SuperAdmin;

/**
 * Role gates for the super admin panel.
 *
 * The platform_owner role implicitly has full access — every check below
 * returns true for an owner. The remaining roles grant access only to their
 * specific area.
 *
 * These are role checks, not model-bound abilities, so they are registered as
 * Gates (Gate::define) in AppServiceProvider rather than via Gate::policy — a
 * policy dispatch expects a model instance, which these abilities do not take.
 * Controllers invoke them with:
 *
 *     Gate::forUser(auth('superadmin')->user())->authorize('supportAccess');
 *
 * Always forUser(auth('superadmin')->user()) — never $this->authorize(), which
 * resolves the empty default (web) guard.
 *
 * Authorization is sourced from Spatie roles (guard 'superadmin'); the role
 * column on the model is a denormalised convenience and not consulted here.
 */
class SuperAdminPolicy
{
    /**
     * Full-platform access — platform_owner only.
     */
    public function platformOwner(SuperAdmin $admin): bool
    {
        return $admin->hasRole(SuperAdmin::ROLE_PLATFORM_OWNER);
    }

    /**
     * Subscriptions, payments, revenue, plans — platform_owner or billing_manager.
     */
    public function billingAccess(SuperAdmin $admin): bool
    {
        return $admin->hasRole([
            SuperAdmin::ROLE_PLATFORM_OWNER,
            SuperAdmin::ROLE_BILLING_MANAGER,
        ]);
    }

    /**
     * Tenant management, impersonation, support tools — platform_owner or
     * support_agent.
     */
    public function supportAccess(SuperAdmin $admin): bool
    {
        return $admin->hasRole([
            SuperAdmin::ROLE_PLATFORM_OWNER,
            SuperAdmin::ROLE_SUPPORT_AGENT,
        ]);
    }

    /**
     * CMS / content — platform_owner or content_manager.
     */
    public function contentAccess(SuperAdmin $admin): bool
    {
        return $admin->hasRole([
            SuperAdmin::ROLE_PLATFORM_OWNER,
            SuperAdmin::ROLE_CONTENT_MANAGER,
        ]);
    }
}
