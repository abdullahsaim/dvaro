<?php

namespace App\Modules\Workshop\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Validates and rate-limits a mechanic-portal login attempt.
 *
 * A mechanic may sign in with either a full password OR a quick PIN — at least
 * one must be supplied. Rate limiting mirrors the tenant LoginRequest: keyed on
 * email + IP (5 attempts), NOT per tenant, so slug spoofing can't reset the
 * counter. Tenant scoping of the actual lookup is enforced in the controller.
 */
class MechanicLoginRequest extends FormRequest
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
            // Either credential may be used; require at least one of them.
            'password' => ['nullable', 'required_without:pin', 'string'],
            'pin' => ['nullable', 'required_without:password', 'string'],
        ];
    }

    /**
     * Throttle key for this attempt: lowercased email + client IP.
     */
    public function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->input('email')).'|'.$this->ip());
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
