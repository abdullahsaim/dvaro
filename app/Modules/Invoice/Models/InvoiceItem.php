<?php

namespace App\Modules\Invoice\Models;

use App\Modules\Fleet\Models\Vehicle;
use App\Traits\HasTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * InvoiceItem — a single line on an invoice.
 *
 * Tenant-owned: uses HasTenant so every query is scoped to the bound tenant and
 * tenant_id is auto-populated on create. amount is in CENTS (integer).
 *
 * vehicle_id is optional but, when set, the FK is restrictOnDelete: fleet
 * reporting (utilisation, most-profitable-vehicle) depends on this link
 * surviving, so a vehicle can never be deleted while invoiced against it.
 */
class InvoiceItem extends Model
{
    use HasTenant;

    protected $fillable = [
        'invoice_id',
        'tenant_id',
        'description',
        'amount',
        'vehicle_id',
        'period_start',
        'period_end',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer', // cents
            'period_start' => 'date',
            'period_end' => 'date',
        ];
    }

    // The tenant() relationship is provided by the HasTenant trait.

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }
}
