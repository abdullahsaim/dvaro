<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Thrown when a mid-cycle vehicle change is not permitted — the agreement is not
 * in a billable (signed/active) state, the chosen vehicle is not available, the
 * new vehicle is the same as the current one, or there is no open invoice to
 * prorate.
 *
 * Renders as HTTP 422 (well-formed request, but the current state forbids it)
 * with a stable machine-readable code for the frontend.
 */
class VehicleChangeNotAllowedException extends RuntimeException
{
    public function __construct(string $message = '')
    {
        parent::__construct(
            $message !== ''
                ? $message
                : 'This vehicle change cannot be performed.'
        );
    }

    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $this->getMessage(),
            'code' => 'VEHICLE_CHANGE_NOT_ALLOWED',
        ], 422);
    }
}
