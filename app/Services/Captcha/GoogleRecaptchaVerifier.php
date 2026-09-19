<?php

namespace App\Services\Captcha;

use App\Contracts\CaptchaVerifierInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Google reCAPTCHA v2 ("I'm not a robot" checkbox) — server-side verification
 * via the siteverify endpoint. Keys are platform-wide (.env RECAPTCHA_*).
 *
 * A missing/rejected token → failed. A network error / non-2xx from Google →
 * unavailable (the lead is kept but flagged) so a Google outage never loses a
 * genuine enquiry.
 */
class GoogleRecaptchaVerifier implements CaptchaVerifierInterface
{
    private const ENDPOINT = 'https://www.google.com/recaptcha/api/siteverify';

    public function __construct(
        private readonly string $secretKey,
        private readonly ?string $publicKey,
    ) {}

    public function verify(?string $token, ?string $ip = null): string
    {
        if (blank($token)) {
            return self::RESULT_FAILED;
        }

        try {
            $response = Http::asForm()
                ->timeout(5)
                ->post(self::ENDPOINT, array_filter([
                    'secret' => $this->secretKey,
                    'response' => $token,
                    'remoteip' => $ip,
                ]));
        } catch (Throwable $e) {
            Log::warning('reCAPTCHA unreachable', ['message' => $e->getMessage()]);

            return self::RESULT_UNAVAILABLE;
        }

        if (! $response->successful()) {
            Log::warning('reCAPTCHA siteverify error', ['status' => $response->status()]);

            return self::RESULT_UNAVAILABLE;
        }

        return $response->json('success') === true ? self::RESULT_PASSED : self::RESULT_FAILED;
    }

    public function siteKey(): ?string
    {
        return $this->publicKey;
    }
}
