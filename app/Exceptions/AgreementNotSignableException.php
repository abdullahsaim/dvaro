<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Thrown when an agreement cannot be signed — it is not in draft status (already
 * signed, active, completed, or cancelled). Agreements are immutable: a signed
 * agreement is never re-signed; a change produces a new draft version instead.
 *
 * Renders as HTTP 422 (the request is well-formed but the agreement's state
 * forbids the action) with a stable machine-readable code for the frontend.
 */
class AgreementNotSignableException extends RuntimeException
{
    public function __construct(string $message = '')
    {
        parent::__construct(
            $message !== ''
                ? $message
                : 'This agreement cannot be signed. Only a draft agreement can be signed.'
        );
    }

    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $this->getMessage(),
            'code' => 'AGREEMENT_NOT_SIGNABLE',
        ], 422);
    }
}
