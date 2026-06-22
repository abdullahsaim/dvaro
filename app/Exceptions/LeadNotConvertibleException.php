<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Thrown when a lead cannot be converted into a customer — it has already been
 * converted, was rejected/expired, or was never submitted.
 *
 * Renders as HTTP 422 (the request is well-formed but the lead's state forbids
 * the action) with a stable machine-readable code so the frontend can react.
 */
class LeadNotConvertibleException extends RuntimeException
{
    public function __construct(string $message = '')
    {
        parent::__construct(
            $message !== ''
                ? $message
                : 'This lead cannot be converted. It must be submitted, not expired, and not already converted or rejected.'
        );
    }

    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $this->getMessage(),
            'code' => 'LEAD_NOT_CONVERTIBLE',
        ], 422);
    }
}
