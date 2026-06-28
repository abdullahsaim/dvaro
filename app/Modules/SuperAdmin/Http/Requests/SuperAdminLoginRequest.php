<?php

namespace App\Modules\SuperAdmin\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Validates and rate-limits a super admin panel login attempt.
 *
 * Mirrors the tenant LoginRequest: keyed on email + IP, 5 attempts. The
 * superadmin guard is global (no tenant), so there is no tenant scoping to
 * apply here — the credential lookup runs against all super admins.
 */
class SuperAdminLoginRequest extends FormRequest
{
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
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ];
    }

    /**
     * Throttle key for this attempt: lowercased email + client IP.
     */
    public function throttleKey(): string
    {
        return 'superadmin|'.Str::transliterate(Str::lower($this->input('email')).'|'.$this->ip());
    }

    /**
     * Throw a validation error if too many failed attempts have been made.
     */
    public function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'email' => __('common.auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }
}
