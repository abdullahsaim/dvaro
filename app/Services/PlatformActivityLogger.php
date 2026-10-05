<?php

namespace App\Services;

use App\Modules\SaasCore\Models\Tenant;
use App\Modules\SuperAdmin\Models\PlatformActivityLog;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * The ONE way to write a platform-wide activity entry. Never create
 * PlatformActivityLog rows directly.
 *
 * Counterpart to AuditLogger, which refuses to write when no tenant is bound
 * (by design — it is a strictly tenant-owned trail). This logger is for the
 * super admin panel's OWN actions: some concern a specific tenant (suspend,
 * plan assigned), some don't (a super admin login, a plan definition edited).
 * Only the keys that actually CHANGED are stored, and secrets are never
 * recorded — same redaction rules as AuditLogger.
 *
 * NEVER THROWS: auditing must not break the action being audited.
 */
class PlatformActivityLogger extends BaseService
{
    /** Keys whose values are never written to the trail. */
    private const REDACTED = ['password', 'password_confirmation', 'token', 'secret', 'api_key'];

    /**
     * @param  array<string, mixed>  $old
     * @param  array<string, mixed>  $new
     */
    public function log(
        string $action,
        ?string $subjectType = null,
        ?int $subjectId = null,
        ?string $subjectLabel = null,
        array $old = [],
        array $new = [],
        ?Tenant $tenant = null,
    ): ?PlatformActivityLog {
        try {
            [$changedOld, $changedNew] = $this->diff($old, $new);
            [$actorType, $actorId, $actorLabel] = $this->actor();

            return PlatformActivityLog::query()->create([
                'tenant_id' => $tenant?->id,
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
            Log::error('PlatformActivityLogger failed', ['action' => $action, 'message' => $e->getMessage()]);

            return null;
        }
    }

    /**
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
     * Almost always a super admin (this logger is called from the super admin
     * panel), but falls back gracefully for a system/job context.
     *
     * @return array{0: string, 1: ?int, 2: ?string}
     */
    private function actor(): array
    {
        $admin = auth('superadmin')->user();
        if ($admin !== null) {
            return [PlatformActivityLog::ACTOR_SUPER_ADMIN, (int) $admin->getAuthIdentifier(), $admin->name ?? $admin->email ?? null];
        }

        $tenantUser = auth('tenant')->user();
        if ($tenantUser !== null) {
            return [PlatformActivityLog::ACTOR_TENANT_USER, (int) $tenantUser->getAuthIdentifier(), $tenantUser->name ?? $tenantUser->email ?? null];
        }

        return [PlatformActivityLog::ACTOR_SYSTEM, null, null];
    }
}
