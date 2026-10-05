<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Thrown when a lead cannot be rejected — it has already been converted into
 * a real customer. Mirrors LeadNotConvertibleException's shape.
 *
 * Renders as HTTP 422 with a stable machine-readable code so the frontend can
 * react, rather than bubbling up as a raw 500.
 */
class LeadNotRejectableException extends RuntimeException
{
    public function __construct(string $message = '')
    {
        parent::__construct(
            $message !== ''
                ? $message
                : 'A converted lead cannot be rejected — it already has a customer behind it.'
        );
    }

    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $this->getMessage(),
            'code' => 'LEAD_NOT_REJECTABLE',
        ], 422);
    }
}
