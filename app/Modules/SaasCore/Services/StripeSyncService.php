<?php

namespace App\Modules\SaasCore\Services;

use App\Modules\SaasCore\Models\Plan;
use App\Modules\SaasCore\Providers\StripePaymentProvider;
use App\Services\BaseService;
use Illuminate\Support\Facades\Log;

/**
 * Mirrors DVARO plans into Stripe Products + recurring Prices, storing the
 * Stripe ids back on the plan row. Run via `php artisan stripe:sync-plans`
 * before going live and after any plan/price change.
 *
 * IDEMPOTENT: re-running is a no-op when nothing changed. Products are created
 * once then updated in place; Prices are IMMUTABLE in Stripe, so a changed
 * plan price archives the old Price and creates a replacement (existing
 * subscriptions keep billing on the archived Price until they move).
 *
 * Free plans are skipped entirely — they never bill through Stripe.
 */
class StripeSyncService extends BaseService
{
    public function __construct(
        private readonly StripePaymentProvider $stripe,
    ) {}

    public function syncPlan(Plan $plan): void
    {
        if ($plan->is_free) {
            return;
        }

        if ($plan->stripe_product_id === null) {
            $plan->stripe_product_id = $this->stripe->createProduct($plan);
        } else {
            $this->stripe->updateProduct($plan->stripe_product_id, $plan);
        }

        $plan->stripe_monthly_price_id = $this->syncPrice(
            $plan, $plan->stripe_monthly_price_id, $plan->priceFor('monthly'), 'month',
        );
        $plan->stripe_annual_price_id = $this->syncPrice(
            $plan, $plan->stripe_annual_price_id, $plan->priceFor('annual'), 'year',
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
                } catch (\Throwable $e) {
                    $failed++;
                    Log::error('stripe:sync-plans failed for plan', [
                        'plan_id' => $plan->id,
                        'plan_slug' => $plan->slug,
                        'error' => $e->getMessage(),
                    ]);
                }
            });

        return ['synced' => $synced, 'failed' => $failed];
    }

    /**
     * Bring one billing cycle's Price in line with the plan. Returns the price
     * id to store (null for a zero-priced cycle).
     */
    private function syncPrice(Plan $plan, ?string $currentPriceId, int $amountCents, string $interval): ?string
    {
        // A zero/unset cycle is not sellable — archive any stale Price.
        if ($amountCents <= 0) {
            if ($currentPriceId !== null) {
                $this->stripe->archivePrice($currentPriceId);
            }

            return null;
        }

        if ($currentPriceId !== null) {
            if ($this->stripe->priceAmount($currentPriceId) === $amountCents) {
                return $currentPriceId; // unchanged — idempotent no-op
            }

            $this->stripe->archivePrice($currentPriceId);
        }

        return $this->stripe->createPrice((string) $plan->stripe_product_id, $amountCents, $interval);
    }
}
