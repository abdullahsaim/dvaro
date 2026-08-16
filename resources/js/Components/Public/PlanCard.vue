<script setup>
// Public pricing card for a single plan. Prices arrive as integer cents and are
// formatted via useCurrency. The enabled-module list is labelled through the
// shared superadmin.plans.module_keys i18n map; key limits (vehicles, staff,
// storage) render human-readably from the plan's limits map. Pricing is always
// live plan data — never hardcoded.
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { useCurrency } from '@/composables/useCurrency.js';

const props = defineProps({
    plan: { type: Object, required: true },
    cycle: { type: String, default: 'monthly' }, // 'monthly' | 'annual'
    featured: { type: Boolean, default: false },
});

const { t } = useI18n();
const { formatAUD } = useCurrency();

const priceCents = computed(() =>
    props.cycle === 'annual' ? props.plan.price_annual : props.plan.price_monthly,
);

const suffix = computed(() =>
    props.cycle === 'annual' ? t('public.pricing.per_year') : t('public.pricing.per_month'),
);

const isFree = computed(() => props.plan.is_free || !priceCents.value);

// Annual saving vs paying monthly for 12 months (only shown on the annual
// cycle when the plan actually discounts it).
const annualSavingCents = computed(() => {
    if (props.cycle !== 'annual' || isFree.value) return 0;
    const monthly = props.plan.price_monthly ?? 0;
    const annual = props.plan.price_annual ?? 0;
    return monthly > 0 && annual > 0 ? monthly * 12 - annual : 0;
});

// Headline limits rendered as feature lines. Order matters — most relevant
// first. A missing/zero limit is treated as not applicable and skipped.
const limitLines = computed(() => {
    const limits = props.plan.limits ?? {};
    return [
        { key: 'max_vehicles', label: 'public.pricing.limits.vehicles' },
        { key: 'max_staff_users', label: 'public.pricing.limits.staff' },
        { key: 'max_customers', label: 'public.pricing.limits.customers' },
        { key: 'max_storage_gb', label: 'public.pricing.limits.storage' },
    ]
        .filter((l) => Number(limits[l.key]) > 0)
        .map((l) => t(l.label, { count: Number(limits[l.key]).toLocaleString('en-AU') }));
});
</script>

<template>
    <div
        class="relative flex flex-col rounded-card border bg-white p-6 shadow-subtle transition duration-300 hover:shadow-pop dark:bg-ink-900"
        :class="featured
            ? 'border-ink-950 ring-1 ring-ink-950 dark:border-ink-100 dark:ring-ink-100'
            : 'border-ink-200 dark:border-ink-800'"
    >
        <span
            v-if="featured"
            class="absolute -top-3 left-1/2 -translate-x-1/2 whitespace-nowrap rounded-full bg-ink-950 px-3 py-1 text-xs font-semibold text-white shadow-subtle dark:bg-ink-50 dark:text-ink-950"
        >
            {{ t('public.pricing.most_popular') }}
        </span>

        <h3 class="text-lg font-semibold text-ink-900 dark:text-ink-50">{{ plan.name }}</h3>
        <p v-if="plan.description" class="mt-1 text-sm text-ink-500">{{ plan.description }}</p>

        <div class="mt-4 flex items-baseline gap-1">
            <span v-if="isFree" class="text-3xl font-bold text-ink-900 dark:text-ink-50">{{ t('public.pricing.free') }}</span>
            <template v-else>
                <Transition name="price-swap" mode="out-in">
                    <span :key="cycle" class="text-3xl font-bold tabular-nums text-ink-900 dark:text-ink-50">{{ formatAUD(priceCents) }}</span>
                </Transition>
                <span class="text-sm text-ink-500">{{ suffix }}</span>
            </template>
        </div>

        <p v-if="annualSavingCents > 0" class="mt-1.5 inline-flex items-center gap-1.5 text-xs font-medium text-success-600 dark:text-success-500">
            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18 9 11.25l4.306 4.306a11.95 11.95 0 0 1 5.814-5.518l2.74-1.22m0 0-5.94-2.281m5.94 2.28-2.28 5.941" />
            </svg>
            {{ t('public.pricing.save_annual', { amount: formatAUD(annualSavingCents) }) }}
        </p>

        <p v-if="plan.trial_days > 0" class="mt-1 text-xs font-medium text-ink-600 dark:text-ink-300">
            {{ t('public.pricing.trial_days', { days: plan.trial_days }) }}
        </p>

        <div class="mt-5 flex-1">
            <p class="text-xs font-semibold uppercase tracking-wide text-ink-400">{{ t('public.pricing.includes') }}</p>
            <ul class="mt-2 space-y-1.5">
                <li v-for="line in limitLines" :key="line" class="flex items-center gap-2 text-sm text-ink-700 dark:text-ink-200">
                    <svg class="h-4 w-4 shrink-0 text-ink-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                    </svg>
                    {{ line }}
                </li>
                <li v-for="moduleKey in plan.modules" :key="moduleKey" class="flex items-center gap-2 text-sm text-ink-700 dark:text-ink-200">
                    <svg class="h-4 w-4 shrink-0 text-success-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m5 13 4 4L19 7" />
                    </svg>
                    {{ t(`superadmin.plans.module_keys.${moduleKey}`) }}
                </li>
            </ul>
        </div>

        <Link
            href="/register"
            class="mt-6 block rounded-control px-4 py-2.5 text-center text-sm font-semibold transition"
            :class="featured
                ? 'bg-ink-950 text-white hover:bg-ink-800 dark:bg-ink-50 dark:text-ink-950 dark:hover:bg-ink-200'
                : 'border border-ink-300 text-ink-900 hover:bg-ink-100 dark:border-ink-700 dark:text-white dark:hover:bg-ink-800'"
        >
            {{ t('public.pricing.get_started') }}
        </Link>
    </div>
</template>

<style scoped>
.price-swap-enter-active,
.price-swap-leave-active {
    transition: opacity 0.15s ease, transform 0.15s ease;
}
.price-swap-enter-from {
    opacity: 0;
    transform: translateY(6px);
}
.price-swap-leave-to {
    opacity: 0;
    transform: translateY(-6px);
}
@media (prefers-reduced-motion: reduce) {
    .price-swap-enter-active,
    .price-swap-leave-active {
        transition: none;
    }
}
</style>
