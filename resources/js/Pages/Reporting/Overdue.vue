<script setup>
// Overdue payments report. No date range (always "as of now"). Design-system pass.
import { Head } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import DataTable from '@/Components/UI/DataTable.vue';
import ReportNav from '@/Components/Reporting/ReportNav.vue';
import ReportToolbar from '@/Components/Reporting/ReportToolbar.vue';
import { useCurrency } from '@/composables/useCurrency';

defineProps({
    rows: { type: Array, default: () => [] },
    exports: { type: Array, default: () => [] },
});

const { t } = useI18n();
const { formatAUD } = useCurrency();
</script>

<template>
    <AppLayout>
        <Head :title="t('reporting.nav.overdue')" />

        <PageHeader :title="t('reporting.nav.overdue')" />
        <ReportNav active="overdue" />
        <ReportToolbar report-type="overdue" :exports="exports" :with-date-range="false" />

        <DataTable :columns="4" :empty="!rows.length">
            <template #head>
                <th class="px-4 py-2">{{ t('reporting.overdue.customer') }}</th>
                <th class="px-4 py-2 text-right">{{ t('reporting.overdue.total') }}</th>
                <th class="px-4 py-2 text-right">{{ t('reporting.overdue.outstanding') }}</th>
                <th class="px-4 py-2 text-right">{{ t('reporting.overdue.days_overdue') }}</th>
            </template>
            <tr v-for="row in rows" :key="row.invoice_id" class="text-ink-700 dark:text-ink-200">
                <td class="px-4 py-2">{{ row.customer }}</td>
                <td class="px-4 py-2 text-right tabular-nums">{{ formatAUD(row.total) }}</td>
                <td class="px-4 py-2 text-right font-semibold tabular-nums text-danger-600 dark:text-danger-500">{{ formatAUD(row.outstanding) }}</td>
                <td class="px-4 py-2 text-right tabular-nums">{{ row.days_overdue }}</td>
            </tr>
            <template #empty>
                <div class="px-4 py-6 text-center text-sm text-ink-400">{{ t('reporting.overdue.empty') }}</div>
            </template>
        </DataTable>
    </AppLayout>
</template>
