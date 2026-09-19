<?php

namespace App\Modules\Fleet\Models;

use App\Traits\HasTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * One odometer reading for a vehicle — APPEND-ONLY history.
 *
 * Written ONLY by RecordOdometerReadingAction (which also moves the vehicle's
 * current_odometer forward). Rows are never updated or deleted: the model
 * refuses both, so the history stays an honest record.
 */
class OdometerReading extends Model
{
    use HasTenant;

    public const SOURCE_MANUAL = 'manual';
    public const SOURCE_SERVICE_LOG = 'service_log';
    /** Reserved for the future return-inspection workflow (Rental module). */
    public const SOURCE_RETURN_INSPECTION = 'return_inspection';

    public const SOURCES = [
        self::SOURCE_MANUAL,
        self::SOURCE_SERVICE_LOG,
        self::SOURCE_RETURN_INSPECTION,
    ];

    public const ACTOR_TENANT_USER = 'tenant_user';
    public const ACTOR_MECHANIC = 'mechanic';

    protected $fillable = [
        'tenant_id',
        'vehicle_id',
        'reading',
        'source',
        'recorded_by_type',
        'recorded_by_id',
        'service_log_id',
        'recorded_at',
    ];

    protected function casts(): array
    {
        return [
            'reading' => 'integer',
            'recorded_by_id' => 'integer',
            'recorded_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Odometer readings are append-only.'));
        static::deleting(fn () => throw new LogicException('Odometer readings are append-only.'));
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }
}
