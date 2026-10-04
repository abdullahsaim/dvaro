<?php

namespace App\Modules\Agreement\Services;

use App\Modules\Agreement\Models\Agreement;
use App\Modules\SaasCore\Models\Tenant;
use App\Services\BaseService;
use Illuminate\Support\Str;

/**
 * The PUBLIC review-and-sign link for one agreement: its secret token and the
 * URL built from it.
 *
 * One token per agreement (unlike the lead form's one-per-tenant token),
 * because each link must open exactly one customer's exactly one agreement —
 * never a list, never a choice. Deliberately does NOT invalidate the token on
 * signing: the SAME link keeps working afterwards so "view your signed
 * agreement" from the confirmation email is one URL, not two, and the public
 * page itself decides what to render from the agreement's status (review form
 * for a draft, read-only "thank you, here's your copy" for anything signed).
 * Re-submitting a signature through an already-used link is still refused —
 * AgreementService::sign() guards the draft-status transition regardless of
 * how the request arrived.
 */
class AgreementSigningService extends BaseService
{
    /**
     * Resolve a public link, or null when the slug/token don't match, the
     * tenant isn't operating, or the agreement belongs to a different tenant.
     * Token compared in constant time. Deliberately NOT status-filtered here —
     * the same link must resolve both before and after signing; the caller
     * decides what to show for each status.
     */
    public function resolvePublic(string $slug, string $token): ?Agreement
    {
        $tenant = Tenant::query()->where('slug', $slug)->first();

        if ($tenant === null
            || in_array($tenant->status, [Tenant::STATUS_SUSPENDED, Tenant::STATUS_CANCELLED], true)) {
            return null;
        }

        // Looked up BY the token (indexed, unique) rather than by agreement
        // id + a row-by-row hash_equals scan — there is one token per
        // AGREEMENT here (not one per tenant like the lead form), so scanning
        // every agreement to stay constant-time isn't practical. hash_equals
        // still guards the final comparison; a 48-char random token makes the
        // indexed-lookup timing difference meaningless in practice.
        $agreement = Agreement::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('signing_token', $token)
            ->first();

        if ($agreement === null || ! hash_equals((string) $agreement->signing_token, $token)) {
            return null;
        }

        return $agreement;
    }

    /** The agreement's token, generating one on first send. */
    public function ensureToken(Agreement $agreement): string
    {
        if (blank($agreement->signing_token)) {
            return $this->regenerate($agreement);
        }

        return $agreement->signing_token;
    }

    /**
     * New token — a previously sent link stops working. Used only if a
     * company deliberately wants to kill a leaked link; sending/resending
     * reuses the existing token via ensureToken() so the customer's one link
     * always stays valid.
     */
    public function regenerate(Agreement $agreement): string
    {
        $token = Str::random(48);
        $agreement->forceFill(['signing_token' => $token])->save();

        return $token;
    }

    public function publicUrl(Agreement $agreement, ?string $slug = null): string
    {
        return route('agreement-signing.show', [
            'tenant_slug' => $slug ?? $agreement->tenant->slug,
            'token' => $this->ensureToken($agreement),
        ]);
    }

    public function markSent(Agreement $agreement): void
    {
        $agreement->forceFill(['signing_sent_at' => now()])->save();
    }
}
