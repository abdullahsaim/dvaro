<script setup>
// Fleet utilisation report — colour-coded utilisation %. Design-system pass.
import { Head } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import DataTable from '@/Components/UI/DataTable.vue';
import ReportNav from '@/Components/Reporting/ReportNav.vue';
import ReportToolbar from '@/Components/Reporting/ReportToolbar.vue';

defineProps({
    rows: { type: Array, default: () => [] },
    filters: { type: Object, default: null },
    exports: { type: Array, default: () => [] },
});

const { t } = useI18n();

// green > 70 · yellow 40–70 · red < 40
function utilisationClass(pct) {
    if (pct > 70) return 'text-success-600 dark:text-success-500';
    if (pct >= 40) return 'text-warning-600 dark:text-warning-500';
    return 'text-danger-600 dark:text-danger-500';
}
</script>

<template>
    <AppLayout>
        <Head :title="t('reporting.nav.fleet')" />

        <PageHeader :title="t('reporting.nav.fleet')" />
        <ReportNav active="fleet" />
        <ReportToolbar report-type="fleet" :filters="filters" :exports="exports" />

        <DataTable :columns="4" :empty="!rows.length">
            <template #head>
                <th class="px-4 py-2">{{ t('reporting.fleet.vehicle') }}</th>
                <th class="px-4 py-2 text-right">{{ t('reporting.fleet.total_days') }}</th>
                <th class="px-4 py-2 text-right">{{ t('reporting.fleet.days_rented') }}</th>
                <th class="px-4 py-2 text-right">{{ t('reporting.fleet.utilisation') }}</th>
            </template>
            <tr v-for="row in rows" :key="row.vehicle" class="text-ink-700 dark:text-ink-200">
                <td class="px-4 py-2">{{ row.vehicle }}</td>
                <td class="px-4 py-2 text-right tabular-nums">{{ row.total_days }}</td>
                <td class="px-4 py-2 text-right tabular-nums">{{ row.days_rented }}</td>
                <td class="px-4 py-2 text-right font-semibold tabular-nums" :class="utilisationClass(row.utilisation)">
                    {{ row.utilisation }}%
                </td>
            </tr>
            <template #empty>
                <div class="px-4 py-6 text-center text-sm text-ink-400">{{ t('reporting.fleet.empty') }}</div>
            </template>
        </DataTable>
    </AppLayout>
</template>
