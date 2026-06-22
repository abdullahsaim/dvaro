<?php

namespace App\Modules\SaasCore\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Validates and rate-limits a public tenant self-registration attempt.
 *
 * This is a PRE-TENANT request: it is served from the web route group with no
 * TenantMiddleware, so there is no bound current_tenant here. The throttle is
 * keyed purely on client IP — 3 attempts per hour — to blunt automated signups.
 */
class TenantRegistrationRequest extends FormRequest
{
    /**
     * Registration is public. Decay window (seconds) for the IP throttle.
     */
    private const MAX_ATTEMPTS = 3;
    private const DECAY_SECONDS = 3600; // 1 hour

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'company_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            // Optional — onboarding falls back to the default plan when omitted.
            // No plan-selection UI yet (this session).
            'plan_id' => ['nullable', 'integer', 'exists:plans,id'],
        ];
    }

    /**
     * Throttle key for this attempt: client IP only (no email — a spammer
     * varying the email must not get a fresh allowance each time).
     */
    public function throttleKey(): string
    {
        return Str::transliterate('register|'.$this->ip());
    }

    /**
     * Throw a validation error if this IP has exceeded the hourly limit.
     */
    public function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), self::MAX_ATTEMPTS)) {
            return;
        }

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'email' => __('common.register.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    /**
     * Record one attempt against the hourly IP limit. Counts every attempt
     * (not just failures) so successful spam signups are throttled too.
     */
    public function hitRateLimiter(): void
    {
        RateLimiter::hit($this->throttleKey(), self::DECAY_SECONDS);
    }
}
