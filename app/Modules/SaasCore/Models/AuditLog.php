<?php

namespace App\Modules\SaasCore\Models;

use App\Exceptions\AppendOnlyException;
use App\Traits\HasTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * AuditLog — APPEND-ONLY record of who changed what (CLAUDE.md).
 *
 * Tenant-owned via HasTenant. Written ONLY through AuditLogger. Like the
 * ledger, it refuses update() and delete() at every layer so the trail can
 * never be rewritten; created_at is the only timestamp (no updated_at).
 */
class AuditLog extends Model
{
    use HasTenant;

    public const UPDATED_AT = null;

    /** Loose actor + subject type tags. */
    public const ACTOR_TENANT_USER = 'tenant_user';

    public const ACTOR_SUPER_ADMIN = 'super_admin';

    public const ACTOR_MECHANIC = 'mechanic';

    public const ACTOR_CUSTOMER = 'customer';

    public const ACTOR_SYSTEM = 'system';

    public const SUBJECT_SETTINGS = 'settings';

    public const SUBJECT_TENANT_USER = 'tenant_user';

    public const SUBJECT_INVITATION = 'tenant_user_invitation';

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
        static::updating(fn () => throw AppendOnlyException::modify('Audit logs'));
        static::deleting(fn () => throw AppendOnlyException::remove('Audit logs'));
    }

    public function update(array $attributes = [], array $options = []): bool
    {
        throw AppendOnlyException::modify('Audit logs');
    }

    public function delete(): ?bool
    {
        throw AppendOnlyException::remove('Audit logs');
    }

    public function scopeForSubject(Builder $query, string $type, ?int $id = null): Builder
    {
        return $query->where('subject_type', $type)
            ->when($id !== null, fn ($q) => $q->where('subject_id', $id));
    }
}
