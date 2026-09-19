<?php

namespace App\Modules\Notification\Models;

use App\Traits\HasTenant;
use Illuminate\Database\Eloquent\Model;

/**
 * Idempotency ledger for FleetReminderService: one row per reminder SENT for a
 * specific (vehicle, kind, stage, due value). Its presence means "already
 * reminded" — a changed due date/km (new due_key) re-arms automatically.
 */
class FleetReminder extends Model
{
    use HasTenant;

    public const KIND_REGISTRATION = 'registration';
    public const KIND_INSURANCE = 'insurance';
    public const KIND_SERVICE_DATE = 'service_date';
    public const KIND_SERVICE_KM = 'service_km';

    public const STAGE_DUE_SOON = 'due_soon';
    public const STAGE_OVERDUE = 'overdue';

    protected $fillable = [
        'tenant_id',
        'vehicle_id',
        'kind',
        'stage',
        'due_key',
        'recipient_count',
        'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'recipient_count' => 'integer',
            'sent_at' => 'datetime',
        ];
    }
}
