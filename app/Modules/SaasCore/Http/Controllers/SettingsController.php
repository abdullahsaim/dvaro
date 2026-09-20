<?php

namespace App\Modules\SaasCore\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Invoice\Services\InvoiceTemplateService;
use App\Modules\SaasCore\Models\AuditLog;
use App\Modules\SaasCore\Models\TenantUser;
use App\Modules\SaasCore\Models\TenantUserInvitation;
use App\Modules\SaasCore\Services\TenantSettingsService;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Settings hub — one page listing every settings area, with the ones this
 * user may open. Replaces the old sidebar link that jumped straight to
 * notification settings.
 *
 * Each section enforces its own permissions in its own controller; the flags
 * here only decide what is SHOWN.
 */
class SettingsController extends Controller
{
    public function index(TenantSettingsService $settings): Response
    {
        $user = auth('tenant')->user();
        $tenant = app('current_tenant');
        $isAdmin = $user instanceof TenantUser && $user->role === TenantUser::ROLE_ADMIN;
        $isFinance = $isAdmin || ($user instanceof TenantUser && $user->role === TenantUser::ROLE_ACCOUNTS);

        $all = $settings->all($tenant);

        return Inertia::render('Settings/Index', [
            'isAdmin' => $isAdmin,
            'isFinance' => $isFinance,
            // A short "current value" line under each card.
            'summary' => [
                'company_name' => $tenant->name,
                'has_logo' => filled($all['logo_path']),
                'staff_count' => TenantUser::query()->where('is_active', true)->count(),
                'pending_invitations' => TenantUserInvitation::query()->pending()->count(),
                'timezone' => $all['timezone'],
                'currency' => $all['currency'],
                'late_fees_enabled' => (bool) $all['late_fees_enabled'],
                'email_provider' => $all['email_provider'],
                'ai_provider' => $all['ai_provider'],
                'lead_form_enabled' => (bool) $all['lead_form_enabled'],
                'invoice_layout' => is_array($all['invoice_template'] ?? null)
                    ? ($all['invoice_template']['layout'] ?? InvoiceTemplateService::LAYOUT_CLASSIC)
                    : InvoiceTemplateService::LAYOUT_CLASSIC,
            ],
            'recentActivity' => $isAdmin
                ? AuditLog::query()->latest('created_at')->limit(5)->get(
                    ['id', 'action', 'subject_label', 'actor_label', 'created_at'],
                )
                : [],
        ]);
    }

    /** Full audit trail (admin only) — read-only, newest first. */
    public function activity(): Response
    {
        $this->authorizeAdmin();

        return Inertia::render('Settings/Activity', [
            'entries' => AuditLog::query()
                ->latest('created_at')
                ->paginate(30)
                ->through(fn (AuditLog $log) => [
                    'id' => $log->id,
                    'action' => $log->action,
                    'subject_type' => $log->subject_type,
                    'subject_label' => $log->subject_label,
                    'actor_label' => $log->actor_label ?? __('common.audit.system'),
                    'old_values' => $log->old_values,
                    'new_values' => $log->new_values,
                    'ip' => $log->ip,
                    'created_at' => $log->created_at,
                ]),
        ]);
    }

    private function authorizeAdmin(): void
    {
        $user = auth('tenant')->user();

        abort_unless($user instanceof TenantUser && $user->role === TenantUser::ROLE_ADMIN, 403);
    }
}
