<script setup>
// Workshop performance report — totals + per-mechanic breakdown. Design-system pass.
import { Head } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import StatCard from '@/Components/UI/StatCard.vue';
import DataTable from '@/Components/UI/DataTable.vue';
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

        <PageHeader :title="t('reporting.nav.workshop')" />
        <ReportNav active="workshop" />
        <ReportToolbar report-type="workshop" :filters="filters" :exports="exports" />

        <dl class="mb-8 grid grid-cols-2 gap-4 lg:grid-cols-4">
            <StatCard :label="t('reporting.workshop.total_jobs')" :value="report.total_jobs" />
            <StatCard :label="t('reporting.workshop.total_labour')" :value="formatAUD(report.total_labour_cost)" />
            <StatCard :label="t('reporting.workshop.total_parts')" :value="formatAUD(report.total_parts_cost)" />
            <StatCard :label="t('reporting.workshop.avg_duration')" :value="`${report.average_duration_hours} ${t('reporting.workshop.hours')}`" />
        </dl>

        <h2 class="mb-3 text-lg font-medium text-ink-900 dark:text-ink-50">{{ t('reporting.workshop.by_mechanic') }}</h2>
        <DataTable :columns="5" :empty="!report.by_mechanic.length">
            <template #head>
                <th class="px-4 py-2">{{ t('reporting.workshop.mechanic') }}</th>
                <th class="px-4 py-2 text-right">{{ t('reporting.workshop.jobs') }}</th>
                <th class="px-4 py-2 text-right">{{ t('reporting.workshop.labour') }}</th>
                <th class="px-4 py-2 text-right">{{ t('reporting.workshop.parts') }}</th>
                <th class="px-4 py-2 text-right">{{ t('reporting.workshop.total') }}</th>
            </template>
            <tr v-for="row in report.by_mechanic" :key="row.mechanic" class="text-ink-700 dark:text-ink-200">
                <td class="px-4 py-2">{{ row.mechanic }}</td>
                <td class="px-4 py-2 text-right tabular-nums">{{ row.jobs }}</td>
                <td class="px-4 py-2 text-right tabular-nums">{{ formatAUD(row.labour_cost) }}</td>
                <td class="px-4 py-2 text-right tabular-nums">{{ formatAUD(row.parts_cost) }}</td>
                <td class="px-4 py-2 text-right font-semibold tabular-nums">{{ formatAUD(row.total_cost) }}</td>
            </tr>
            <template #empty>
                <div class="px-4 py-6 text-center text-sm text-ink-400">{{ t('reporting.workshop.empty') }}</div>
            </template>
        </DataTable>
    </AppLayout>
</template>
