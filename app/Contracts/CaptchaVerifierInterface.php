<?php

namespace App\Contracts;

/**
 * Contract for captcha verification (Google reCAPTCHA v2 today).
 *
 * Never call the captcha provider directly — resolve this interface. verify()
 * NEVER throws; it returns one of the RESULT_* values:
 *   passed      — the provider confirmed a human
 *   failed      — the provider rejected the token (missing / invalid / expired)
 *   unavailable — not configured, or the provider could not be reached; the
 *                 caller accepts the submission but flags it as unverified
 */
interface CaptchaVerifierInterface
{
    public const RESULT_PASSED = 'passed';
    public const RESULT_FAILED = 'failed';
    public const RESULT_UNAVAILABLE = 'unavailable';

    public function verify(?string $token, ?string $ip = null): string;

    /** Public site key for the browser widget, or null when not configured. */
    public function siteKey(): ?string;
}
