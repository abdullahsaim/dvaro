<?php

namespace App\Modules\Workshop\Models;

use App\Traits\HasTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A photo or document attached to a workshop job. SENSITIVE — `path` points
 * to the default (private) disk, never served directly; always through
 * FileUrlService's signed, expiring URL. The only sanctioned writer is
 * UploadServiceLogDocumentAction.
 */
class ServiceLogDocument extends Model
{
    use HasTenant;

    public const UPLOADED_BY_MECHANIC = 'mechanic';

    public const UPLOADED_BY_TENANT_USER = 'tenant_user';

    protected $fillable = [
        'tenant_id',
        'service_log_id',
        'path',
        'original_name',
        'uploaded_by_type',
        'uploaded_by_id',
    ];

    public function serviceLog(): BelongsTo
    {
        return $this->belongsTo(ServiceLog::class);
    }
}
