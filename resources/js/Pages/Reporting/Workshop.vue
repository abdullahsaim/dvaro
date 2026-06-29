<script setup>
// Workshop performance report — totals + per-mechanic breakdown. FUNCTIONAL ONLY.
import { Head } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import AppLayout from '@/Layouts/AppLayout.vue';
import ReportNav from '@/Components/Reporting/ReportNav.vue';
import ReportToolbar from '@/Components/Reporting/ReportToolbar.vue';
import { useCurrency } from '@/composables/useCurrency';

defineProps({
    report: { type: Object, required: true },
    filters: { type: Object, default: null },
    exports: { type: Array, default: () => [] },
});

const { t } = useI18n();
const { formatAUD } = useCurrency();
</script>

<template>
    <AppLayout>
        <Head :title="t('reporting.nav.workshop')" />

        <div class="py-10">
            <h1 class="text-2xl font-semibold">{{ t('reporting.nav.workshop') }}</h1>
            <ReportNav active="workshop" class="mt-6" />
            <ReportToolbar report-type="workshop" :filters="filters" :exports="exports" />

            <dl class="mb-8 grid grid-cols-2 gap-4 lg:grid-cols-4">
                <div class="rounded border border-slate-200 p-4 dark:border-slate-800">
                    <dt class="text-sm text-slate-500 dark:text-slate-400">{{ t('reporting.workshop.total_jobs') }}</dt>
                    <dd class="mt-1 text-xl font-semibold">{{ report.total_jobs }}</dd>
                </div>
                <div class="rounded border border-slate-200 p-4 dark:border-slate-800">
                    <dt class="text-sm text-slate-500 dark:text-slate-400">{{ t('reporting.workshop.total_labour') }}</dt>
                    <dd class="mt-1 text-xl font-semibold">{{ formatAUD(report.total_labour_cost) }}</dd>
                </div>
                <div class="rounded border border-slate-200 p-4 dark:border-slate-800">
                    <dt class="text-sm text-slate-500 dark:text-slate-400">{{ t('reporting.workshop.total_parts') }}</dt>
                    <dd class="mt-1 text-xl font-semibold">{{ formatAUD(report.total_parts_cost) }}</dd>
                </div>
                <div class="rounded border border-slate-200 p-4 dark:border-slate-800">
                    <dt class="text-sm text-slate-500 dark:text-slate-400">{{ t('reporting.workshop.avg_duration') }}</dt>
                    <dd class="mt-1 text-xl font-semibold">{{ report.average_duration_hours }} {{ t('reporting.workshop.hours') }}</dd>
                </div>
            </dl>

            <h2 class="mb-3 text-lg font-medium">{{ t('reporting.workshop.by_mechanic') }}</h2>
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-slate-200 text-left text-slate-500 dark:border-slate-800">
                        <th class="py-2">{{ t('reporting.workshop.mechanic') }}</th>
                        <th class="py-2 text-right">{{ t('reporting.workshop.jobs') }}</th>
                        <th class="py-2 text-right">{{ t('reporting.workshop.labour') }}</th>
                        <th class="py-2 text-right">{{ t('reporting.workshop.parts') }}</th>
                        <th class="py-2 text-right">{{ t('reporting.workshop.total') }}</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="row in report.by_mechanic" :key="row.mechanic" class="border-b border-slate-100 dark:border-slate-800/60">
                        <td class="py-2">{{ row.mechanic }}</td>
                        <td class="py-2 text-right">{{ row.jobs }}</td>
                        <td class="py-2 text-right">{{ formatAUD(row.labour_cost) }}</td>
                        <td class="py-2 text-right">{{ formatAUD(row.parts_cost) }}</td>
                        <td class="py-2 text-right font-semibold">{{ formatAUD(row.total_cost) }}</td>
                    </tr>
                    <tr v-if="!report.by_mechanic.length">
                        <td colspan="5" class="py-3 text-slate-400">{{ t('reporting.workshop.empty') }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </AppLayout>
</template>
