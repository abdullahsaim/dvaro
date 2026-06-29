<?php

namespace App\Modules\Reporting\Models;

use App\Modules\SaasCore\Models\TenantUser;
use App\Traits\HasTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * ReportExport — one queued report-file generation request and its result.
 *
 * Tenant-owned: uses HasTenant so every query is scoped to the bound tenant and
 * tenant_id is auto-populated on create. The row is created (status=pending) by
 * the controller, then finalised (ready/failed + file_path) by
 * GenerateReportExportJob.
 */
class ReportExport extends Model
{
    use HasTenant;

    public const STATUS_PENDING = 'pending';
    public const STATUS_READY = 'ready';
    public const STATUS_FAILED = 'failed';

    public const FORMAT_PDF = 'pdf';
    public const FORMAT_EXCEL = 'excel';

    public const FORMATS = [
        self::FORMAT_PDF,
        self::FORMAT_EXCEL,
    ];

    protected $fillable = [
        'tenant_id',
        'requested_by',
        'report_type',
        'format',
        'parameters',
        'status',
        'file_path',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'parameters' => 'array',
            'expires_at' => 'datetime',
        ];
    }

    // The tenant() relationship is provided by the HasTenant trait.

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(TenantUser::class, 'requested_by');
    }

    public function isReady(): bool
    {
        return $this->status === self::STATUS_READY;
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }
}
