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
        class="flex flex-col rounded-2xl border p-6 shadow-sm transition"
        :class="featured
            ? 'border-indigo-500 ring-1 ring-indigo-500 dark:border-indigo-400'
            : 'border-slate-200 dark:border-slate-800'"
    >
        <h3 class="text-lg font-semibold">{{ plan.name }}</h3>
        <p v-if="plan.description" class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ plan.description }}</p>

        <div class="mt-4 flex items-baseline gap-1">
            <span v-if="isFree" class="text-3xl font-bold">{{ t('public.pricing.free') }}</span>
            <template v-else>
                <span class="text-3xl font-bold">{{ formatAUD(priceCents) }}</span>
                <span class="text-sm text-slate-500 dark:text-slate-400">{{ suffix }}</span>
            </template>
        </div>

        <p v-if="plan.trial_days > 0" class="mt-1 text-xs font-medium text-indigo-600 dark:text-indigo-400">
            {{ t('public.pricing.trial_days', { days: plan.trial_days }) }}
        </p>

        <div class="mt-5">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">{{ t('public.pricing.includes') }}</p>
            <ul class="mt-2 space-y-1.5">
                <li v-for="moduleKey in plan.modules" :key="moduleKey" class="flex items-center gap-2 text-sm">
                    <svg class="h-4 w-4 shrink-0 text-emerald-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m5 13 4 4L19 7" />
                    </svg>
                    {{ t(`superadmin.plans.module_keys.${moduleKey}`) }}
                </li>
            </ul>
        </div>

        <Link
            href="/register"
            class="mt-6 block rounded-lg px-4 py-2.5 text-center text-sm font-semibold transition"
            :class="featured
                ? 'bg-indigo-600 text-white hover:bg-indigo-500'
                : 'border border-slate-300 text-slate-900 hover:bg-slate-100 dark:border-slate-700 dark:text-white dark:hover:bg-slate-800'"
        >
            {{ t('public.pricing.get_started') }}
        </Link>
    </div>
</template>
