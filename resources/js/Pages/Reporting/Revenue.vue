<script setup>
// Revenue report — CSS bar chart by month + revenue-by-vehicle table.
// No charting library (CLAUDE.md VPS constraint). FUNCTIONAL ONLY.
import { computed } from 'vue';
import { Head } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import AppLayout from '@/Layouts/AppLayout.vue';
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

        <div class="py-10">
            <h1 class="text-2xl font-semibold">{{ t('reporting.nav.revenue') }}</h1>
            <ReportNav active="revenue" class="mt-6" />
            <ReportToolbar report-type="revenue" :filters="filters" :exports="exports" />

            <h2 class="mb-3 text-lg font-medium">{{ t('reporting.revenue.by_month') }}</h2>
            <div v-if="byPeriod.length" class="flex h-56 items-end gap-3 rounded border border-slate-200 p-4 dark:border-slate-800">
                <div v-for="row in byPeriod" :key="row.month" class="flex flex-1 flex-col items-center justify-end">
                    <span class="mb-1 text-xs text-slate-500">{{ formatAUD(row.revenue) }}</span>
                    <div
                        class="w-full rounded-t bg-indigo-500 transition-all dark:bg-indigo-400"
                        :style="{ height: barHeight(row.revenue) }"
                        :title="formatAUD(row.revenue)"
                    />
                    <span class="mt-1 text-xs text-slate-400">{{ row.month }}</span>
                </div>
            </div>
            <p v-else class="text-sm text-slate-400">{{ t('reporting.revenue.empty') }}</p>

            <h2 class="mb-3 mt-8 text-lg font-medium">{{ t('reporting.revenue.by_vehicle') }}</h2>
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-slate-200 text-left text-slate-500 dark:border-slate-800">
                        <th class="py-2">{{ t('reporting.revenue.vehicle') }}</th>
                        <th class="py-2 text-right">{{ t('reporting.revenue.days_rented') }}</th>
                        <th class="py-2 text-right">{{ t('reporting.revenue.amount') }}</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="row in byVehicle" :key="row.vehicle" class="border-b border-slate-100 dark:border-slate-800/60">
                        <td class="py-2">{{ row.vehicle }}</td>
                        <td class="py-2 text-right">{{ row.days_rented }}</td>
                        <td class="py-2 text-right">{{ formatAUD(row.revenue) }}</td>
                    </tr>
                    <tr v-if="!byVehicle.length">
                        <td colspan="3" class="py-3 text-slate-400">{{ t('reporting.revenue.empty') }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </AppLayout>
</template>
