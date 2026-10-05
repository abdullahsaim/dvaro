<?php

namespace App\Modules\SuperAdmin\Models;

use App\Exceptions\AppendOnlyException;
use App\Modules\SaasCore\Models\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * PlatformActivityLog — APPEND-ONLY, platform-wide counterpart to AuditLog.
 *
 * Deliberately NOT tenant-scoped (no HasTenant, no TenantScope): a super admin
 * reading this screen must see every tenant's events in one place, and some
 * rows (super admin login, a plan definition created) have no tenant at all.
 * Written ONLY through PlatformActivityLogger. Like AuditLog and the ledger,
 * refuses update()/delete() at every layer — the trail can never be rewritten.
 */
class PlatformActivityLog extends Model
{
    public const UPDATED_AT = null;

    public const ACTOR_SUPER_ADMIN = 'super_admin';

    public const ACTOR_TENANT_USER = 'tenant_user';

    public const ACTOR_SYSTEM = 'system';

    public const SUBJECT_TENANT = 'tenant';

    public const SUBJECT_PLAN = 'plan';

    public const SUBJECT_SUBSCRIPTION = 'subscription';

    public const SUBJECT_SETTINGS = 'platform_settings';

    protected $fillable = [
        'tenant_id',
        'action',
        'subject_type',
        'subject_id',
        'subject_label',
        'actor_type',
        'actor_id',
        'actor_label',
        'old_values',
        'new_values',
        'ip',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'subject_id' => 'integer',
            'actor_id' => 'integer',
            'old_values' => 'array',
            'new_values' => 'array',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw AppendOnlyException::modify('Platform activity logs'));
        static::deleting(fn () => throw AppendOnlyException::remove('Platform activity logs'));
    }

    public function update(array $attributes = [], array $options = []): bool
    {
        throw AppendOnlyException::modify('Platform activity logs');
    }

    public function delete(): ?bool
    {
        throw AppendOnlyException::remove('Platform activity logs');
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
