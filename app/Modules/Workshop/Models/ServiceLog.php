<?php

namespace App\Modules\Workshop\Models;

use App\Modules\Fleet\Models\Vehicle;
use App\Traits\HasTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * ServiceLog — one workshop job against one vehicle, logged by one mechanic.
 *
 * Status lifecycle is driven through ChangeServiceLogStatusAction; completing a
 * log returns the vehicle to 'available' (via ChangeVehicleStatusAction) and
 * fires MaintenanceCompleted. total_cost is the cached sum of labour + parts.
 */
class ServiceLog extends Model
{
    use HasTenant;
    use SoftDeletes;

    public const STATUS_PENDING = 'pending';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_WAITING_FOR_PARTS = 'waiting_for_parts';
    public const STATUS_RE_INSPECTION_REQUIRED = 're_inspection_required';

    /** The complete set of valid statuses (CLAUDE.md — 5 maintenance statuses). */
    public const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_IN_PROGRESS,
        self::STATUS_COMPLETED,
        self::STATUS_WAITING_FOR_PARTS,
        self::STATUS_RE_INSPECTION_REQUIRED,
    ];

    protected $fillable = [
        'tenant_id',
        'vehicle_id',
        'mechanic_id',
        'status',
        'title',
        'description',
        'odometer_reading',
        'labour_cost',
        'total_cost',
        'started_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'odometer_reading' => 'integer',
            'labour_cost' => 'integer', // cents
            'total_cost' => 'integer',  // cents
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function mechanic(): BelongsTo
    {
        return $this->belongsTo(Mechanic::class);
    }

    public function parts(): HasMany
    {
        return $this->hasMany(PartUsed::class);
    }

    public function isComplete(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    /**
     * Sum of all parts used on this log, in cents.
     */
    public function totalPartsCost(): int
    {
        return (int) $this->parts()->sum('total_cost');
    }
}
