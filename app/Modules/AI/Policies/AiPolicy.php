<?php

namespace App\Modules\AI\Policies;

use App\Modules\AI\Models\AiConversation;
use App\Modules\SaasCore\Models\TenantUser;

/**
 * Authorization for AI conversations.
 *
 * Two layers of isolation:
 *   1. TENANT — TenantScope already 404s a conversation from another tenant on
 *      route-model binding, and every check below re-asserts same-tenant as
 *      defence-in-depth.
 *   2. USER — within a tenant, a user may only see and act on their OWN
 *      conversations. This is the extra requirement for the AI module: staff A
 *      must never read staff B's chats (which may contain business-intelligence
 *      data they queried).
 *
 * IMPORTANT: evaluated against the TENANT guard user. The controller calls
 * Gate::forUser(auth('tenant')->user())->authorize(...) — see AiController.
 */
class AiPolicy
{
    public function viewAny(TenantUser $user): bool
    {
        return true;
    }

    public function create(TenantUser $user): bool
    {
        return true;
    }

    public function view(TenantUser $user, AiConversation $conversation): bool
    {
        return $this->ownsWithinTenant($user, $conversation);
    }

    /** Posting a chat turn into an existing conversation. */
    public function chat(TenantUser $user, AiConversation $conversation): bool
    {
        return $this->ownsWithinTenant($user, $conversation);
    }

    public function delete(TenantUser $user, AiConversation $conversation): bool
    {
        return $this->ownsWithinTenant($user, $conversation);
    }

    private function ownsWithinTenant(TenantUser $user, AiConversation $conversation): bool
    {
        return (int) $user->tenant_id === (int) $conversation->tenant_id
            && (int) $user->id === (int) $conversation->user_id;
    }
}
