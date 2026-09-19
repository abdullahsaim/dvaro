<script setup>
// Expenses report — totals incl./ex GST, by category, by month (active
// expenses only). PDF + Excel export via the shared ReportToolbar (queued).
import { computed } from 'vue';
import { Head } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import StatCard from '@/Components/UI/StatCard.vue';
import DataTable from '@/Components/UI/DataTable.vue';
import ReportNav from '@/Components/Reporting/ReportNav.vue';
import ReportToolbar from '@/Components/Reporting/ReportToolbar.vue';
import { useCurrency } from '@/composables/useCurrency';

const props = defineProps({
    report: { type: Object, required: true }, // { totals, by_category[], by_month[] }
    filters: { type: Object, default: null },
    exports: { type: Array, default: () => [] },
});

const { t } = useI18n();
const { formatAUD } = useCurrency();

const monthFormat = new Intl.DateTimeFormat('en-AU', { month: 'short', year: 'numeric' });
function monthLabel(ym) {
    const [y, m] = ym.split('-').map(Number);
    return monthFormat.format(new Date(y, m - 1, 1));
}

const maxMonth = computed(() => Math.max(1, ...props.report.by_month.map((r) => r.total)));
</script>

<template>
    <AppLayout>
        <Head :title="t('reporting.nav.expenses')" />

        <PageHeader :title="t('reporting.nav.expenses')" />
        <ReportNav active="expenses" />
        <ReportToolbar report-type="expenses" :filters="filters" :exports="exports" />

        <dl class="mb-8 grid grid-cols-2 gap-4 lg:grid-cols-4">
            <StatCard :label="t('expenses.stats.count')" :value="report.totals.count" />
            <StatCard :label="t('expenses.stats.total')" :value="formatAUD(report.totals.total)" />
            <StatCard :label="t('expenses.stats.gst')" :value="formatAUD(report.totals.gst)" />
            <StatCard :label="t('expenses.stats.ex_gst')" :value="formatAUD(report.totals.ex_gst)" />
        </dl>

        <h2 class="mb-3 text-lg font-medium text-ink-900 dark:text-ink-50">{{ t('expenses.by_category') }}</h2>
        <DataTable :columns="5" :empty="!report.by_category.length">
            <template #head>
                <th class="px-4 py-2">{{ t('expenses.fields.category') }}</th>
                <th class="px-4 py-2 text-right">{{ t('expenses.stats.count') }}</th>
                <th class="px-4 py-2 text-right">{{ t('expenses.fields.total') }}</th>
                <th class="px-4 py-2 text-right">{{ t('expenses.fields.gst') }}</th>
                <th class="px-4 py-2 text-right">{{ t('expenses.fields.ex_gst') }}</th>
            </template>
            <tr v-for="row in report.by_category" :key="row.category" class="text-ink-700 dark:text-ink-200">
                <td class="px-4 py-2">{{ row.category }}</td>
                <td class="px-4 py-2 text-right tabular-nums">{{ row.count }}</td>
                <td class="px-4 py-2 text-right font-medium tabular-nums">{{ formatAUD(row.total) }}</td>
                <td class="px-4 py-2 text-right tabular-nums text-ink-500">{{ formatAUD(row.gst) }}</td>
                <td class="px-4 py-2 text-right tabular-nums text-ink-500">{{ formatAUD(row.ex_gst) }}</td>
            </tr>
            <template #empty>
                <div class="px-4 py-6 text-center text-sm text-ink-400">{{ t('expenses.empty_period') }}</div>
            </template>
        </DataTable>

        <h2 class="mb-3 mt-8 text-lg font-medium text-ink-900 dark:text-ink-50">{{ t('expenses.by_month') }}</h2>
        <div v-if="report.by_month.length" class="rounded-card border border-ink-200 bg-white p-4 shadow-subtle dark:border-ink-800 dark:bg-ink-900">
            <div v-for="row in report.by_month" :key="row.month" class="grid grid-cols-[6rem_1fr_7rem] items-center gap-3 py-1.5 text-sm">
                <span class="text-ink-500">{{ monthLabel(row.month) }}</span>
                <div class="h-2 overflow-hidden rounded-full bg-ink-100 dark:bg-ink-800">
                    <div class="h-full rounded-full bg-ink-900 transition-all duration-300 dark:bg-ink-100" :style="{ width: `${(row.total / maxMonth) * 100}%` }" />
                </div>
                <span class="text-right font-medium tabular-nums text-ink-900 dark:text-ink-50">{{ formatAUD(row.total) }}</span>
            </div>
        </div>
        <p v-else class="text-sm text-ink-400">{{ t('expenses.empty_period') }}</p>
    </AppLayout>
</template>
