<?php

namespace App\Services\Captcha;

use App\Contracts\CaptchaVerifierInterface;

/**
 * Used when reCAPTCHA keys are not configured (local / testing / a deploy
 * without keys). Never blocks: every submission is "unavailable", so leads are
 * accepted but flagged unverified — the honeypot, timing check and rate limits
 * still apply.
 */
class NullCaptchaVerifier implements CaptchaVerifierInterface
{
    public function verify(?string $token, ?string $ip = null): string
    {
        return self::RESULT_UNAVAILABLE;
    }

    public function siteKey(): ?string
    {
        return null;
    }
}
