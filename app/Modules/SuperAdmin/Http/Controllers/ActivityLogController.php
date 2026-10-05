<?php

namespace App\Modules\SuperAdmin\Http\Controllers;

use App\Http\Controllers\Concerns\PaginatesForUser;
use App\Http\Controllers\Controller;
use App\Modules\SuperAdmin\Models\PlatformActivityLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Platform-wide activity log — read-only oversight of every logged
 * cross-tenant action (tenant lifecycle, plan assignment/definition changes,
 * impersonation, platform settings). Owner-level only (platformOwner): this
 * view aggregates billing, support, and settings activity that no single
 * narrower role (support_agent, billing_manager, content_manager) should see
 * all of in one place.
 */
class ActivityLogController extends Controller
{
    use PaginatesForUser;

    public function index(Request $request): Response
    {
        Gate::forUser(auth('superadmin')->user())->authorize('platformOwner');

        $action = trim((string) $request->query('action', ''));
        $search = trim((string) $request->query('search', ''));

        $logs = PlatformActivityLog::query()
            ->with('tenant:id,name,slug')
            ->when($action !== '', fn ($q) => $q->where('action', $action))
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($inner) use ($search) {
                    $inner->where('subject_label', 'like', "%{$search}%")
                        ->orWhere('actor_label', 'like', "%{$search}%");
                });
            })
            ->orderByDesc('created_at')
            ->orderByDesc('id') // stable tiebreak when two rows land in the same second
            ->paginate($this->perPage(30))
            ->withQueryString()
            ->through(fn (PlatformActivityLog $log) => [
                'id' => $log->id,
                'action' => $log->action,
                'subject_label' => $log->subject_label,
                'actor_label' => $log->actor_label,
                'actor_type' => $log->actor_type,
                'tenant' => $log->tenant ? ['name' => $log->tenant->name, 'slug' => $log->tenant->slug] : null,
                'old_values' => $log->old_values,
                'new_values' => $log->new_values,
                'ip' => $log->ip,
                'created_at' => $log->created_at,
            ]);

        // Every distinct action ever logged — drives the filter dropdown.
        $actions = PlatformActivityLog::query()
            ->select('action')
            ->distinct()
            ->orderBy('action')
            ->pluck('action');

        return Inertia::render('SuperAdmin/Activity/Index', [
            'logs' => $logs,
            'actions' => $actions,
            'filters' => [
                'action' => $action,
                'search' => $search,
            ],
        ]);
    }
}
