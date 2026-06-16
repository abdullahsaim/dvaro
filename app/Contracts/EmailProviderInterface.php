<?php

namespace App\Contracts;

use Illuminate\Mail\Mailable;

/**
 * Contract for email providers (Mailgun, Resend, SMTP).
 *
 * Never call provider SDKs directly — resolve a provider for the tenant and
 * dispatch through this interface. All sends must be queued.
 */
interface EmailProviderInterface
{
    public function send(string $to, Mailable $mailable): void;
}
