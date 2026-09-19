<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Thrown when a tenant action would breach a hard plan limit.
 *
 * Plan limits are HARD BLOCKS, never soft warnings (per CLAUDE.md). This
 * renders as an HTTP 403 with a stable machine-readable code so the frontend
 * can surface an upgrade prompt.
 *
 * JSON callers (API / fetch) get that 403 payload. Browser + Inertia form
 * submissions are redirected BACK with the message both as a `plan_limit`
 * validation error (inline on the form) and an `error` flash (toast) — a raw
 * JSON 403 would otherwise render as an error modal inside the Inertia page.
 */
class PlanLimitExceededException extends RuntimeException
{
    public function __construct(
        public readonly string $limitKey,
        public readonly int $limit,
        public readonly int $current,
        string $message = ''
    ) {
        parent::__construct(
            $message !== ''
                ? $message
                : "Plan limit reached for '{$limitKey}' ({$current}/{$limit}). Upgrade your plan to continue."
        );
    }

    public function render(Request $request): JsonResponse|RedirectResponse
    {
        if (! $request->expectsJson()) {
            return back()
                ->withInput()
                ->withErrors(['plan_limit' => $this->getMessage()])
                ->with('error', $this->getMessage());
        }

        return response()->json([
            'success' => false,
            'message' => $this->getMessage(),
            'code' => 'PLAN_LIMIT_REACHED',
            'upgrade_required' => true,
        ], 403);
    }
}
