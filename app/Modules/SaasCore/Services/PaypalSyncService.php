<?php

namespace App\Modules\SaasCore\Services;

use App\Modules\SaasCore\Models\Plan;
use App\Modules\SaasCore\Providers\PaypalPaymentProvider;
use App\Services\BaseService;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Mirrors DVARO plans into PayPal catalog Products + recurring billing Plans,
 * storing the PayPal ids back on the plan row. Run via
 * `php artisan paypal:sync-plans` before going live and after any plan/price
 * change. Mirrors StripeSyncService exactly.
 *
 * IDEMPOTENT: re-running is a no-op when nothing changed. PayPal billing Plans
 * are immutable on price, so a changed plan price deactivates the old Plan
 * and creates a replacement (existing subscriptions keep billing on the
 * deactivated Plan until they move). PayPal has no "update product" call for
 * the fields DVARO cares about, so the Product is only ever created once.
 *
 * Free plans are skipped entirely — they never bill through PayPal.
 */
class PaypalSyncService extends BaseService
{
    public function __construct(
        private readonly PaypalPaymentProvider $paypal,
    ) {}

    public function syncPlan(Plan $plan): void
    {
        if ($plan->is_free) {
            return;
        }

        if ($plan->paypal_product_id === null) {
            $plan->paypal_product_id = $this->paypal->createProduct($plan);
        }

        $plan->paypal_monthly_plan_id = $this->syncBillingPlan(
            $plan, $plan->paypal_monthly_plan_id, $plan->priceFor('monthly'), 'MONTH',
        );
        $plan->paypal_annual_plan_id = $this->syncBillingPlan(
            $plan, $plan->paypal_annual_plan_id, $plan->priceFor('annual'), 'YEAR',
        );

        $plan->save();
    }

    /**
     * Sync every active plan. Per-plan failures are logged and skipped so one
     * bad plan never aborts the batch. Returns [synced => int, failed => int].
     */
    public function syncAllPlans(): array
    {
        $synced = 0;
        $failed = 0;

        Plan::query()->where('is_active', true)->orderBy('id')->get()
            ->each(function (Plan $plan) use (&$synced, &$failed): void {
                try {
                    $this->syncPlan($plan);
                    $synced++;
                } catch (Throwable $e) {
                    $failed++;
                    Log::error('paypal:sync-plans failed for plan', [
                        'plan_id' => $plan->id,
                        'plan_slug' => $plan->slug,
                        'error' => $e->getMessage(),
                    ]);
                }
            });

        return ['synced' => $synced, 'failed' => $failed];
    }

    /**
     * Bring one billing cycle's billing Plan in line with the plan. Returns
     * the plan id to store (null for a zero-priced cycle).
     */
    private function syncBillingPlan(Plan $plan, ?string $currentPlanId, int $amountCents, string $interval): ?string
    {
        // A zero/unset cycle is not sellable — deactivate any stale Plan.
        if ($amountCents <= 0) {
            if ($currentPlanId !== null) {
                $this->paypal->deactivateBillingPlan($currentPlanId);
            }

            return null;
        }

        if ($currentPlanId !== null) {
            if ($this->paypal->billingPlanAmount($currentPlanId) === $amountCents) {
                return $currentPlanId; // unchanged — idempotent no-op
            }

            $this->paypal->deactivateBillingPlan($currentPlanId);
        }

        return $this->paypal->createBillingPlan((string) $plan->paypal_product_id, $amountCents, $interval);
    }
}
