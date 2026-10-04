<?php

namespace App\Modules\Rental\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Agreement\Models\Agreement;
use App\Modules\Rental\DTOs\CompleteReturnDTO;
use App\Modules\Rental\Http\Requests\StoreReturnInspectionRequest;
use App\Modules\Rental\Services\RentalReturnService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

/**
 * Records a vehicle's return and settles its bond — thin controller: validate
 * → RentalReturnService → redirect. See AgreementController's authorization
 * note: every action goes through Gate::forUser(auth('tenant')->user()),
 * never $this->authorize().
 */
class ReturnInspectionController extends Controller
{
    public function store(
        StoreReturnInspectionRequest $request,
        Agreement $agreement,
        RentalReturnService $service,
    ): RedirectResponse {
        Gate::forUser(auth('tenant')->user())->authorize('returnVehicle', $agreement);

        abort_if($agreement->returnInspection()->exists(), 422, __('common.rental.already_returned'));

        // Captured BEFORE the service runs: completeReturn() dispatches
        // ReturnInspectionCompleted/BondRefunded, and their queued listener's
        // forgetTenant() (QueuedNotificationListener) clears the current_tenant
        // binding under a sync queue connection — see the same note on
        // PublicAgreementSigningController::submit().
        $tenantSlug = app('current_tenant')->slug;

        $service->completeReturn(
            $agreement,
            CompleteReturnDTO::fromRequest($request, auth('tenant')->id()),
        );

        return redirect()
            ->route('tenant.agreements.show', [
                'tenant_slug' => $tenantSlug,
                'agreement' => $agreement->id,
            ])
            ->with('success', __('common.rental.return_recorded'));
    }
}
