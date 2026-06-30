<?php

namespace App\Modules\CMS\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\CMS\Events\DemoRequestSubmitted;
use App\Modules\CMS\Http\Requests\DemoRequestFormRequest;
use App\Modules\CMS\Models\DemoRequest;
use Illuminate\Http\RedirectResponse;

/**
 * Public "request a demo" capture from the landing site.
 *
 * PUBLIC: no auth. Spam is curbed by the per-IP route throttle (3/hour). The
 * record is reviewed from the super admin panel.
 */
class DemoRequestController extends Controller
{
    public function store(DemoRequestFormRequest $request): RedirectResponse
    {
        $demoRequest = DemoRequest::query()->create([
            ...$request->validated(),
            'status' => DemoRequest::STATUS_NEW,
        ]);

        DemoRequestSubmitted::dispatch($demoRequest);

        return back()->with('success', __('common.cms.demo_requested'));
    }
}
