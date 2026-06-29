<?php

namespace App\Modules\Customer\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Validates and rate-limits a customer-portal login attempt.
 *
 * Rate limiting is keyed on email + IP + tenant slug (5 attempts). The slug is
 * included because the SAME email may identify different customers across
 * tenants — each (customer, tenant) login bucket is throttled independently, so
 * a lockout on one tenant's portal never blocks a different customer who happens
 * to share the address. Tenant scoping of the actual auth lookup is enforced in
 * the controller/guard (explicit tenant_id on top of TenantScope).
 */
class CustomerLoginRequest extends FormRequest
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
     * Throttle key: lowercased email + client IP + tenant slug.
     */
    public function throttleKey(): string
    {
        return Str::transliterate(
            Str::lower($this->input('email')).'|'.$this->ip().'|'.$this->route('tenant_slug')
        );
    }

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
