<?php

namespace App\Http\Controllers;

use App\Modules\SaasCore\Providers\StripePaymentProvider;
use App\Modules\SaasCore\Services\StripeWebhookService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * POST /stripe/webhook — Stripe's server-to-server event endpoint.
 *
 * NO auth middleware and NO CSRF (excluded in bootstrap/app.php): the verified
 * webhook signature is the ONLY authentication. Any signature/payload problem
 * aborts 400 before a byte of processing.
 *
 * After verification the response is 200 even when processing fails: Stripe
 * retries on non-200, and a retry storm cannot fix a code bug — the failure is
 * reported + logged for a human instead. Every received event is logged.
 */
class StripeWebhookController extends Controller
{
    public function handle(
        Request $request,
        StripePaymentProvider $provider,
        StripeWebhookService $service,
    ): Response {
        try {
            $event = $provider->constructWebhookEvent(
                $request->getContent(),
                (string) $request->header('Stripe-Signature'),
            );
        } catch (Throwable $e) {
            Log::warning('Stripe webhook rejected: invalid signature or payload', [
                'error' => $e->getMessage(),
                'ip' => $request->ip(),
            ]);

            return response('Invalid signature.', 400);
        }

        Log::info('Stripe webhook received', [
            'event_id' => $event->id ?? null,
            'type' => $event->type ?? null,
        ]);

        try {
            $service->handle($event);
        } catch (Throwable $e) {
            report($e);
            Log::error('Stripe webhook processing failed', [
                'event_id' => $event->id ?? null,
                'type' => $event->type ?? null,
                'error' => $e->getMessage(),
            ]);
        }

        return response('Webhook handled.', 200);
    }
}
