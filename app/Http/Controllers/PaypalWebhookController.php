<?php

namespace App\Http\Controllers;

use App\Modules\SaasCore\Providers\PaypalPaymentProvider;
use App\Modules\SaasCore\Services\PaypalWebhookService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * POST /paypal/webhook — PayPal's server-to-server event endpoint. Mirrors
 * StripeWebhookController exactly.
 *
 * NO auth middleware and NO CSRF (excluded in bootstrap/app.php): the verified
 * webhook signature is the ONLY authentication. Any signature/payload problem
 * aborts 400 before a byte of processing.
 *
 * After verification the response is 200 even when processing fails: PayPal
 * retries on non-200, and a retry storm cannot fix a code bug — the failure is
 * reported + logged for a human instead. Every received event is logged.
 */
class PaypalWebhookController extends Controller
{
    public function handle(
        Request $request,
        PaypalPaymentProvider $provider,
        PaypalWebhookService $service,
    ): Response {
        try {
            $event = $provider->constructWebhookEvent($request->getContent(), [
                'transmission_id' => (string) $request->header('Paypal-Transmission-Id'),
                'transmission_time' => (string) $request->header('Paypal-Transmission-Time'),
                'cert_url' => (string) $request->header('Paypal-Cert-Url'),
                'auth_algo' => (string) $request->header('Paypal-Auth-Algo'),
                'transmission_sig' => (string) $request->header('Paypal-Transmission-Sig'),
            ]);
        } catch (Throwable $e) {
            Log::warning('PayPal webhook rejected: invalid signature or payload', [
                'error' => $e->getMessage(),
                'ip' => $request->ip(),
            ]);

            return response('Invalid signature.', 400);
        }

        Log::info('PayPal webhook received', [
            'event_id' => $event->id ?? null,
            'type' => $event->event_type ?? null,
        ]);

        try {
            $service->handle($event);
        } catch (Throwable $e) {
            report($e);
            Log::error('PayPal webhook processing failed', [
                'event_id' => $event->id ?? null,
                'type' => $event->event_type ?? null,
                'error' => $e->getMessage(),
            ]);
        }

        return response('Webhook handled.', 200);
    }
}
