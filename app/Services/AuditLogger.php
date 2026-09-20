<?php

namespace App\Services;

use App\Modules\SaasCore\Models\AuditLog;
use App\Modules\SaasCore\Models\Tenant;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * The ONE way to write an audit entry. Never create AuditLog rows directly.
 *
 * Resolves the acting user across all four guards (tenant, super admin,
 * mechanic, customer) and falls back to "system" for jobs and commands. Only
 * the keys that actually CHANGED are stored, and secrets are never recorded.
 *
 * NEVER THROWS: auditing must not break the action being audited. A failure is
 * logged and swallowed — a missing audit row is bad, a failed settings save
 * because of auditing is worse.
 */
class AuditLogger extends BaseService
{
    /** Keys whose values are never written to the trail. */
    private const REDACTED = ['password', 'password_confirmation', 'token', 'secret', 'api_key'];

    /**
     * @param  array<string, mixed>  $old
     * @param  array<string, mixed>  $new
     */
    public function log(
        string $action,
        string $subjectType,
        ?int $subjectId = null,
        ?string $subjectLabel = null,
        array $old = [],
        array $new = [],
        ?Tenant $tenant = null,
    ): ?AuditLog {
        try {
            $tenant ??= app()->bound('current_tenant') ? app('current_tenant') : null;

            if ($tenant === null) {
                return null; // platform-level action with no tenant context
            }

            [$changedOld, $changedNew] = $this->diff($old, $new);
            [$actorType, $actorId, $actorLabel] = $this->actor();

            return AuditLog::query()->create([
                'tenant_id' => $tenant->id,
                'action' => $action,
                'subject_type' => $subjectType,
                'subject_id' => $subjectId,
                'subject_label' => $subjectLabel,
                'actor_type' => $actorType,
                'actor_id' => $actorId,
                'actor_label' => $actorLabel,
                'old_values' => $changedOld ?: null,
                'new_values' => $changedNew ?: null,
                'ip' => request()->ip(),
                'created_at' => now(),
            ]);
        } catch (Throwable $e) {
            Log::error('AuditLogger failed', ['action' => $action, 'message' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * Only the keys present in $new whose value actually changed, redacted.
     *
     * @param  array<string, mixed>  $old
     * @param  array<string, mixed>  $new
     * @return array{0: array<string, mixed>, 1: array<string, mixed>}
     */
    private function diff(array $old, array $new): array
    {
        $changedOld = [];
        $changedNew = [];

        foreach ($new as $key => $value) {
            $before = $old[$key] ?? null;

            if ($before === $value) {
                continue;
            }

            $changedOld[$key] = $this->redact($key, $before);
            $changedNew[$key] = $this->redact($key, $value);
        }

        return [$changedOld, $changedNew];
    }

    private function redact(string $key, mixed $value): mixed
    {
        foreach (self::REDACTED as $secret) {
            if (str_contains(strtolower($key), $secret)) {
                return '••••';
            }
        }

        return $value;
    }

    /**
     * @return array{0: string, 1: ?int, 2: ?string}
     */
    private function actor(): array
    {
        foreach ([
            AuditLog::ACTOR_TENANT_USER => 'tenant',
            AuditLog::ACTOR_SUPER_ADMIN => 'superadmin',
            AuditLog::ACTOR_MECHANIC => 'mechanic',
            AuditLog::ACTOR_CUSTOMER => 'customer',
        ] as $type => $guard) {
            $user = auth($guard)->user();

            if ($user !== null) {
                return [$type, (int) $user->getAuthIdentifier(), $user->name ?? $user->email ?? null];
            }
        }

        return [AuditLog::ACTOR_SYSTEM, null, null];
    }
}
