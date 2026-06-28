<script setup>
// Plan list — pricing, modules, limits, active toggle, edit. FUNCTIONAL ONLY.
import { Head, Link, router } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import SuperAdminLayout from '@/Layouts/SuperAdminLayout.vue';
import { useCurrency } from '@/composables/useCurrency';

const props = defineProps({
    plans: { type: Array, required: true },
});

const { t } = useI18n();
const { formatAUD } = useCurrency();

function toggle(plan) {
    router.post(`/superadmin/plans/${plan.id}/toggle`, {}, { preserveScroll: true });
}
</script>

<template>
    <SuperAdminLayout>
        <Head :title="t('superadmin.plans.title')" />

        <div class="py-10">
            <div class="flex items-center justify-between">
                <h1 class="text-2xl font-semibold">{{ t('superadmin.plans.title') }}</h1>
                <Link href="/superadmin/plans/create" class="rounded bg-indigo-600 px-4 py-2 text-sm text-white">
                    {{ t('superadmin.plans.new_plan') }}
                </Link>
            </div>

            <p v-if="!plans.length" class="mt-8 text-sm text-slate-500 dark:text-slate-400">
                {{ t('superadmin.plans.empty') }}
            </p>

            <div v-else class="mt-6 grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                <div
                    v-for="plan in plans"
                    :key="plan.id"
                    class="flex flex-col rounded-lg border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900"
                >
                    <div class="flex items-start justify-between">
                        <div>
                            <h2 class="text-lg font-semibold">{{ plan.name }}</h2>
                            <p v-if="plan.is_free" class="text-xs text-emerald-600">{{ t('superadmin.plans.is_free') }}</p>
                        </div>
                        <span
                            class="rounded-full px-2.5 py-0.5 text-xs"
                            :class="plan.is_active
                                ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900 dark:text-emerald-200'
                                : 'bg-slate-200 text-slate-600 dark:bg-slate-700 dark:text-slate-300'"
                        >
                            {{ plan.is_active ? t('superadmin.plans.active_badge') : t('superadmin.plans.inactive_badge') }}
                        </span>
                    </div>

                    <p v-if="plan.description" class="mt-2 text-sm text-slate-500 dark:text-slate-400">{{ plan.description }}</p>

                    <div class="mt-4 space-y-1 text-sm">
                        <p>{{ t('superadmin.plans.monthly') }}: <span class="font-medium">{{ formatAUD(plan.price_monthly) }}</span></p>
                        <p>{{ t('superadmin.plans.annual') }}: <span class="font-medium">{{ formatAUD(plan.price_annual) }}</span></p>
                        <p class="text-slate-500 dark:text-slate-400">{{ t('superadmin.plans.trial_days') }}: {{ plan.trial_days }}</p>
                    </div>

                    <div v-if="plan.modules?.length" class="mt-4 flex flex-wrap gap-1">
                        <span
                            v-for="m in plan.modules"
                            :key="m"
                            class="rounded bg-slate-100 px-2 py-0.5 text-xs dark:bg-slate-800"
                        >
                            {{ t(`superadmin.plans.module_keys.${m}`) }}
                        </span>
                    </div>

                    <p class="mt-4 text-xs text-slate-400">
                        {{ t('superadmin.plans.subscriptions_count', { count: plan.subscriptions_count }) }}
                    </p>

                    <div class="mt-4 flex gap-3 border-t border-slate-100 pt-4 text-sm dark:border-slate-800">
                        <Link :href="`/superadmin/plans/${plan.id}/edit`" class="text-indigo-600 hover:underline dark:text-indigo-400">
                            {{ t('superadmin.plans.edit') }}
                        </Link>
                        <button type="button" class="text-slate-600 hover:underline dark:text-slate-300" @click="toggle(plan)">
                            {{ t('superadmin.plans.toggle') }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </SuperAdminLayout>
</template>
