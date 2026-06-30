<?php

namespace App\Modules\SuperAdmin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\CMS\Models\DemoRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Demo / contact request queue (super admin panel).
 *
 * Content-level access (contentAccess gate). DemoRequest is platform-wide
 * (no TenantScope), so implicit route-model binding on {demoRequest} is safe.
 * Authorization uses the load-bearing forUser pattern — never $this->authorize().
 */
class DemoRequestController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::forUser(auth('superadmin')->user())->authorize('contentAccess');

        $status = $request->validate([
            'status' => ['nullable', Rule::in(DemoRequest::STATUSES)],
        ])['status'] ?? null;

        $requests = DemoRequest::query()
            ->when($status, fn ($q) => $q->where('status', $status))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        // Per-status counts for the filter tabs, in one grouped query.
        $counts = DemoRequest::query()
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        return Inertia::render('SuperAdmin/DemoRequests/Index', [
            'requests' => $requests,
            'counts' => $counts,
            'filterStatus' => $status,
        ]);
    }

    /**
     * Mark a request as contacted.
     */
    public function markContacted(DemoRequest $demoRequest): RedirectResponse
    {
        Gate::forUser(auth('superadmin')->user())->authorize('contentAccess');

        $demoRequest->update(['status' => DemoRequest::STATUS_CONTACTED]);

        return back()->with('success', __('common.cms.demo_marked_contacted'));
    }
}
