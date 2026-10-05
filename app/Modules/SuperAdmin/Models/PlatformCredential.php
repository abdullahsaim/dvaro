<?php

namespace App\Modules\SuperAdmin\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * PlatformCredential — a single platform-wide, ALWAYS-ENCRYPTED credential row
 * (Stripe/PayPal secrets, SMTP password, AI/SMS API keys, ...).
 *
 * Deliberately separate from PlatformSetting: reads/writes go exclusively
 * through PlatformCredentialService, which is the only thing allowed to
 * decrypt `value` — never serialise this model directly into an Inertia
 * response (it would ship the encrypted ciphertext to the browser for no
 * reason, and invites a future mistake where someone decrypts it there).
 */
class PlatformCredential extends Model
{
    protected $fillable = [
        'key',
        'value',
    ];

    protected $hidden = [
        'value',
    ];
}
