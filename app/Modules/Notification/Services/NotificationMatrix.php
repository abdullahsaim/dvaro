<?php

namespace App\Modules\Notification\Services;

use App\Modules\Notification\Models\NotificationLog;
use App\Modules\SaasCore\Models\Tenant;
use App\Modules\SaasCore\Services\TenantSettingsService;
use App\Services\BaseService;

/**
 * Which notifications go out, to whom, on which channel.
 *
 * Until now a tenant had three blunt switches (email / SMS / WhatsApp) that
 * applied to everything: a company that wanted SMS payment receipts but no SMS
 * invoice notices had no way to say so. This adds a per-trigger matrix on top:
 *
 *     notification_matrix = ['payment.received' => ['sms' => false], ...]
 *
 * Only DIFFERENCES from the defaults are stored, so a tenant that never opens
 * the page keeps exactly today's behaviour, and adding a new trigger here ships
 * with a sensible default for everyone.
 *
 * Two gates, both of which must pass:
 *   1. the global channel switch (notify_*_enabled) — the master off switch
 *   2. this trigger's own channel flag
 * A channel a trigger does not support (SMS to staff, who have no phone field)
 * is never offered and never allowed.
 */
class NotificationMatrix extends BaseService
{
    public const AUDIENCE_CUSTOMER = 'customer';

    public const AUDIENCE_STAFF = 'staff';

    public const EMAIL = NotificationLog::CHANNEL_EMAIL;

    public const SMS = NotificationLog::CHANNEL_SMS;

    public const WHATSAPP = NotificationLog::CHANNEL_WHATSAPP;

    /**
     * Every trigger the platform can send, with its audience, the channels it
     * supports and what each channel does out of the box (today's behaviour:
     * email on, SMS/WhatsApp follow the tenant's global switches).
     *
     * Labels and descriptions are translated — see lang/en/notifications.php.
     *
     * @var array<string, array{audience: string, channels: array<string, bool>}>
     */
    public const TRIGGERS = [
        'agreement.signed' => [
            'audience' => self::AUDIENCE_CUSTOMER,
            'channels' => [self::EMAIL => true, self::SMS => true, self::WHATSAPP => true],
        ],
        'invoice.generated' => [
            'audience' => self::AUDIENCE_CUSTOMER,
            'channels' => [self::EMAIL => true, self::SMS => true, self::WHATSAPP => true],
        ],
        'invoice.late_fee' => [
            'audience' => self::AUDIENCE_CUSTOMER,
            'channels' => [self::EMAIL => true, self::SMS => true, self::WHATSAPP => true],
        ],
        'payment.received' => [
            'audience' => self::AUDIENCE_CUSTOMER,
            'channels' => [self::EMAIL => true, self::SMS => true, self::WHATSAPP => true],
        ],
        'agreement.expiry' => [
            'audience' => self::AUDIENCE_STAFF,
            'channels' => [self::EMAIL => true],
        ],
        'fleet.reminder_digest' => [
            'audience' => self::AUDIENCE_STAFF,
            'channels' => [self::EMAIL => true],
        ],
        'lead.submitted' => [
            'audience' => self::AUDIENCE_STAFF,
            'channels' => [self::EMAIL => true],
        ],
        'subscription.payment_failed' => [
            'audience' => self::AUDIENCE_STAFF,
            'channels' => [self::EMAIL => true],
        ],
        'subscription.cancelled' => [
            'audience' => self::AUDIENCE_STAFF,
            'channels' => [self::EMAIL => true],
        ],
    ];

    /**
     * Triggers a tenant must never be able to silence — they protect the
     * company's own account, so they are shown but locked.
     */
    public const LOCKED = ['subscription.payment_failed', 'subscription.cancelled'];

    public function __construct(
        private readonly TenantSettingsService $settings,
    ) {}

    /**
     * May this trigger send on this channel for this tenant?
     *
     * Unknown triggers (a one-off like a staff invitation, which is never
     * optional) fall back to the global channel switch alone.
     */
    public function allows(Tenant $tenant, string $trigger, string $channel): bool
    {
        if (! $this->channelEnabled($tenant, $channel)) {
            return false;
        }

        $definition = self::TRIGGERS[$trigger] ?? null;

        if ($definition === null) {
            return true; // not configurable — the global switch already decided
        }

        if (! array_key_exists($channel, $definition['channels'])) {
            return false; // this trigger does not support this channel at all
        }

        if (in_array($trigger, self::LOCKED, true)) {
            return true;
        }

        $override = $this->stored($tenant)[$trigger][$channel] ?? null;

        return $override === null ? $definition['channels'][$channel] : (bool) $override;
    }

    /** The tenant's global switch for a channel. */
    public function channelEnabled(Tenant $tenant, string $channel): bool
    {
        return (bool) match ($channel) {
            self::EMAIL => $this->settings->get($tenant, 'notify_email_enabled'),
            self::SMS => $this->settings->get($tenant, 'notify_sms_enabled'),
            self::WHATSAPP => $this->settings->get($tenant, 'notify_whatsapp_enabled'),
            default => false,
        };
    }

    /**
     * The whole matrix, defaults merged with the tenant's overrides, ready for
     * the settings screen.
     *
     * @return list<array<string, mixed>>
     */
    public function forUi(Tenant $tenant): array
    {
        $stored = $this->stored($tenant);

        $rows = [];

        foreach (self::TRIGGERS as $key => $definition) {
            $channels = [];

            foreach ($definition['channels'] as $channel => $default) {
                $override = $stored[$key][$channel] ?? null;

                $channels[$channel] = [
                    'enabled' => $override === null ? $default : (bool) $override,
                    // Greyed out in the UI with the reason, rather than hidden:
                    // people need to see WHY a switch will not send.
                    'channel_off' => ! $this->channelEnabled($tenant, $channel),
                ];
            }

            $rows[] = [
                'key' => $key,
                'label' => $this->translate($key, 'label'),
                'description' => $this->translate($key, 'description'),
                'audience' => $definition['audience'],
                'locked' => in_array($key, self::LOCKED, true),
                'channels' => $channels,
            ];
        }

        return $rows;
    }

    /**
     * Normalise submitted form input into the stored shape: known triggers and
     * channels only, locked rows dropped, booleans cast.
     *
     * @param  array<string, array<string, mixed>>  $input
     * @return array<string, array<string, bool>>
     */
    public function sanitize(array $input): array
    {
        $clean = [];

        foreach (self::TRIGGERS as $key => $definition) {
            if (in_array($key, self::LOCKED, true) || ! isset($input[$key]) || ! is_array($input[$key])) {
                continue;
            }

            foreach (array_keys($definition['channels']) as $channel) {
                if (array_key_exists($channel, $input[$key])) {
                    $clean[$key][$channel] = filter_var($input[$key][$channel], FILTER_VALIDATE_BOOLEAN);
                }
            }
        }

        return $clean;
    }

    /**
     * A trigger's label / description from lang/{locale}/notifications.php.
     * Event types are dotted ('payment.received') but translation keys are
     * dot-PATHS, so the event type becomes 'payment_received'.
     */
    private function translate(string $trigger, string $field): string
    {
        return __('notifications.triggers.'.str_replace('.', '_', $trigger).'.'.$field);
    }

    /** @return array<string, array<string, bool>> */
    private function stored(Tenant $tenant): array
    {
        $matrix = $this->settings->get($tenant, 'notification_matrix');

        return is_array($matrix) ? $matrix : [];
    }
}
