<?php

namespace App\Modules\Workshop\Models;

use App\Traits\HasTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * PartUsed — a single spare part consumed on a service log.
 *
 * total_cost is derived (quantity × unit_cost) and recalculated automatically
 * on every save, so callers never have to compute it by hand.
 */
class PartUsed extends Model
{
    use HasTenant;

    protected $table = 'parts_used';

    protected $fillable = [
        'tenant_id',
        'service_log_id',
        'name',
        'quantity',
        'unit_cost',
        'total_cost',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_cost' => 'integer',  // cents
            'total_cost' => 'integer', // cents
        ];
    }

    protected static function booted(): void
    {
        // Keep total_cost authoritative: always quantity × unit_cost, regardless
        // of what (if anything) the caller passed in.
        static::saving(function (PartUsed $part): void {
            $part->total_cost = (int) $part->quantity * (int) $part->unit_cost;
        });
    }

    public function serviceLog(): BelongsTo
    {
        return $this->belongsTo(ServiceLog::class);
    }
}
