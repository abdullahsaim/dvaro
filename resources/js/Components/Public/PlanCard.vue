<script setup>
// Public pricing card for a single plan. Prices arrive as integer cents and are
// formatted via useCurrency. The enabled-module list is labelled through the
// shared superadmin.plans.module_keys i18n map. Pricing is always live plan
// data — never hardcoded.
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
</script>

<template>
    <div
        class="flex flex-col rounded-card border p-6 shadow-subtle transition"
        :class="featured
            ? 'border-ink-950 ring-1 ring-ink-950 dark:border-ink-100 dark:ring-ink-100'
            : 'border-ink-200 dark:border-ink-800'"
    >
        <h3 class="text-lg font-semibold text-ink-900 dark:text-ink-50">{{ plan.name }}</h3>
        <p v-if="plan.description" class="mt-1 text-sm text-ink-500">{{ plan.description }}</p>

        <div class="mt-4 flex items-baseline gap-1">
            <span v-if="isFree" class="text-3xl font-bold text-ink-900 dark:text-ink-50">{{ t('public.pricing.free') }}</span>
            <template v-else>
                <span class="text-3xl font-bold text-ink-900 dark:text-ink-50">{{ formatAUD(priceCents) }}</span>
                <span class="text-sm text-ink-500">{{ suffix }}</span>
            </template>
        </div>

        <p v-if="plan.trial_days > 0" class="mt-1 text-xs font-medium text-ink-600 dark:text-ink-300">
            {{ t('public.pricing.trial_days', { days: plan.trial_days }) }}
        </p>

        <div class="mt-5">
            <p class="text-xs font-semibold uppercase tracking-wide text-ink-400">{{ t('public.pricing.includes') }}</p>
            <ul class="mt-2 space-y-1.5">
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
