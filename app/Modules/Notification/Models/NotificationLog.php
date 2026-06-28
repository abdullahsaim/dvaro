<?php

namespace App\Modules\Notification\Models;

use App\Traits\HasTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * NotificationLog — an append-style audit row for every notification ATTEMPT
 * (success or failure) across email, SMS, and WhatsApp.
 *
 * Tenant-owned: uses HasTenant so every query is scoped to the bound tenant and
 * tenant_id is auto-populated on create. NotificationService writes one row per
 * attempt and also sets tenant_id explicitly (it is handed a Tenant), so the
 * write is correct even when no tenant is bound (queue/console context).
 *
 * The notifiable is stored loosely (notifiable_type string + notifiable_id),
 * not as a morphTo: a log must outlive the record it references, and the
 * targets live across different module guards (Customer, TenantUser).
 */
class NotificationLog extends Model
{
    use HasTenant;

    public const STATUS_PENDING = 'pending';
    public const STATUS_SENT = 'sent';
    public const STATUS_FAILED = 'failed';

    public const CHANNEL_EMAIL = 'email';
    public const CHANNEL_SMS = 'sms';
    public const CHANNEL_WHATSAPP = 'whatsapp';

    /** Loose notifiable type tags (notifiable_type column). */
    public const TYPE_CUSTOMER = 'customer';
    public const TYPE_TENANT_USER = 'tenant_user';
    public const TYPE_VEHICLE = 'vehicle';
    public const TYPE_AGREEMENT = 'agreement';

    protected $fillable = [
        'tenant_id',
        'notifiable_type',
        'notifiable_id',
        'channel',
        'event_type',
        'recipient',
        'subject',
        'body',
        'status',
        'provider',
        'provider_message_id',
        'error_message',
        'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'notifiable_id' => 'integer',
            'sent_at' => 'datetime',
        ];
    }

    // The tenant() relationship is provided by the HasTenant trait.

    public function scopeFailed(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_FAILED);
    }

    public function scopeSent(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_SENT);
    }

    public function scopeForNotifiable(Builder $query, string $type, int $id): Builder
    {
        return $query
            ->where('notifiable_type', $type)
            ->where('notifiable_id', $id);
    }
}
