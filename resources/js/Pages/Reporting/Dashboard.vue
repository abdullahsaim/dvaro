<script setup>
// Reporting overview — KPI cards. FUNCTIONAL ONLY, design pass later.
import { Head } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import AppLayout from '@/Layouts/AppLayout.vue';
import ReportNav from '@/Components/Reporting/ReportNav.vue';
import { useCurrency } from '@/composables/useCurrency';

const props = defineProps({
    stats: { type: Object, required: true },
});

const { t } = useI18n();
const { formatAUD } = useCurrency();
</script>

<template>
    <AppLayout>
        <Head :title="t('reporting.title')" />

        <div class="py-10">
            <h1 class="text-2xl font-semibold">{{ t('reporting.title') }}</h1>
            <p class="mt-1 text-slate-500 dark:text-slate-400">{{ t('reporting.subtitle') }}</p>

            <ReportNav active="dashboard" class="mt-6" />

            <dl class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <div class="rounded border border-slate-200 p-4 dark:border-slate-800">
                    <dt class="text-sm text-slate-500 dark:text-slate-400">{{ t('reporting.kpi.revenue_this_month') }}</dt>
                    <dd class="mt-1 text-xl font-semibold">{{ formatAUD(props.stats.revenue_this_month) }}</dd>
                </div>
                <div class="rounded border border-slate-200 p-4 dark:border-slate-800">
                    <dt class="text-sm text-slate-500 dark:text-slate-400">{{ t('reporting.kpi.active_rentals') }}</dt>
                    <dd class="mt-1 text-xl font-semibold">{{ props.stats.active_rentals }}</dd>
                </div>
                <div class="rounded border border-slate-200 p-4 dark:border-slate-800">
                    <dt class="text-sm text-slate-500 dark:text-slate-400">{{ t('reporting.kpi.overdue') }}</dt>
                    <dd class="mt-1 text-xl font-semibold">
                        {{ props.stats.overdue_count }}
                        <span class="text-sm font-normal text-slate-400">· {{ formatAUD(props.stats.overdue_total) }}</span>
                    </dd>
                </div>
                <div class="rounded border border-slate-200 p-4 dark:border-slate-800">
                    <dt class="text-sm text-slate-500 dark:text-slate-400">{{ t('reporting.kpi.fleet_utilisation') }}</dt>
                    <dd class="mt-1 text-xl font-semibold">{{ props.stats.fleet_utilisation }}%</dd>
                </div>
                <div class="rounded border border-slate-200 p-4 dark:border-slate-800">
                    <dt class="text-sm text-slate-500 dark:text-slate-400">{{ t('reporting.kpi.vehicles_due_service') }}</dt>
                    <dd class="mt-1 text-xl font-semibold">{{ props.stats.vehicles_due_service }}</dd>
                </div>
            </dl>
        </div>
    </AppLayout>
</template>
