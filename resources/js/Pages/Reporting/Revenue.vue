<script setup>
// Revenue report — CSS bar chart by month + revenue-by-vehicle table.
// No charting library (CLAUDE.md VPS constraint). Design-system pass.
import { computed } from 'vue';
import { Head } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import DataTable from '@/Components/UI/DataTable.vue';
import ReportNav from '@/Components/Reporting/ReportNav.vue';
import ReportToolbar from '@/Components/Reporting/ReportToolbar.vue';
import { useCurrency } from '@/composables/useCurrency';

const props = defineProps({
    byPeriod: { type: Array, default: () => [] },
    byVehicle: { type: Array, default: () => [] },
    filters: { type: Object, default: null },
    exports: { type: Array, default: () => [] },
});

const { t } = useI18n();
const { formatAUD } = useCurrency();

// Tallest bar = the largest monthly revenue (avoid divide-by-zero).
const maxRevenue = computed(() => Math.max(1, ...props.byPeriod.map((r) => r.revenue)));

function barHeight(revenue) {
    return `${Math.round((revenue / maxRevenue.value) * 100)}%`;
}
</script>

<template>
    <AppLayout>
        <Head :title="t('reporting.nav.revenue')" />

        <PageHeader :title="t('reporting.nav.revenue')" />
        <ReportNav active="revenue" />
        <ReportToolbar report-type="revenue" :filters="filters" :exports="exports" />

        <h2 class="mb-3 text-lg font-medium text-ink-900 dark:text-ink-50">{{ t('reporting.revenue.by_month') }}</h2>
        <div v-if="byPeriod.length" class="flex h-56 items-end gap-3 rounded-card border border-ink-200 bg-white p-4 shadow-subtle dark:border-ink-800 dark:bg-ink-900">
            <div v-for="row in byPeriod" :key="row.month" class="flex flex-1 flex-col items-center justify-end">
                <span class="mb-1 text-xs text-ink-500">{{ formatAUD(row.revenue) }}</span>
                <div
                    class="w-full rounded-t bg-ink-900 transition-all dark:bg-ink-100"
                    :style="{ height: barHeight(row.revenue) }"
                    :title="formatAUD(row.revenue)"
                />
                <span class="mt-1 text-xs text-ink-400">{{ row.month }}</span>
            </div>
        </div>
        <p v-else class="text-sm text-ink-400">{{ t('reporting.revenue.empty') }}</p>

        <h2 class="mb-3 mt-8 text-lg font-medium text-ink-900 dark:text-ink-50">{{ t('reporting.revenue.by_vehicle') }}</h2>
        <DataTable :columns="3" :empty="!byVehicle.length">
            <template #head>
                <th class="px-4 py-2">{{ t('reporting.revenue.vehicle') }}</th>
                <th class="px-4 py-2 text-right">{{ t('reporting.revenue.days_rented') }}</th>
                <th class="px-4 py-2 text-right">{{ t('reporting.revenue.amount') }}</th>
            </template>
            <tr v-for="row in byVehicle" :key="row.vehicle" class="text-ink-700 dark:text-ink-200">
                <td class="px-4 py-2">{{ row.vehicle }}</td>
                <td class="px-4 py-2 text-right tabular-nums">{{ row.days_rented }}</td>
                <td class="px-4 py-2 text-right tabular-nums">{{ formatAUD(row.revenue) }}</td>
            </tr>
            <template #empty>
                <div class="px-4 py-6 text-center text-sm text-ink-400">{{ t('reporting.revenue.empty') }}</div>
            </template>
        </DataTable>
    </AppLayout>
</template>
