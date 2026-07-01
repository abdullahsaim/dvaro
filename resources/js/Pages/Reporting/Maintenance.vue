<script setup>
// Maintenance costs report — labour + parts per vehicle. Design-system pass.
import { Head } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import DataTable from '@/Components/UI/DataTable.vue';
import ReportNav from '@/Components/Reporting/ReportNav.vue';
import { useCurrency } from '@/composables/useCurrency';

defineProps({
    rows: { type: Array, default: () => [] },
    filters: { type: Object, default: null },
});

const { t } = useI18n();
const { formatAUD } = useCurrency();
</script>

<template>
    <AppLayout>
        <Head :title="t('reporting.nav.maintenance')" />

        <PageHeader :title="t('reporting.nav.maintenance')" />
        <ReportNav active="maintenance" />

        <DataTable :columns="5" :empty="!rows.length">
            <template #head>
                <th class="px-4 py-2">{{ t('reporting.maintenance.vehicle') }}</th>
                <th class="px-4 py-2 text-right">{{ t('reporting.maintenance.jobs') }}</th>
                <th class="px-4 py-2 text-right">{{ t('reporting.maintenance.labour') }}</th>
                <th class="px-4 py-2 text-right">{{ t('reporting.maintenance.parts') }}</th>
                <th class="px-4 py-2 text-right">{{ t('reporting.maintenance.total') }}</th>
            </template>
            <tr v-for="row in rows" :key="row.vehicle" class="text-ink-700 dark:text-ink-200">
                <td class="px-4 py-2">{{ row.vehicle }}</td>
                <td class="px-4 py-2 text-right tabular-nums">{{ row.jobs }}</td>
                <td class="px-4 py-2 text-right tabular-nums">{{ formatAUD(row.labour_cost) }}</td>
                <td class="px-4 py-2 text-right tabular-nums">{{ formatAUD(row.parts_cost) }}</td>
                <td class="px-4 py-2 text-right font-semibold tabular-nums">{{ formatAUD(row.total_cost) }}</td>
            </tr>
            <template #empty>
                <div class="px-4 py-6 text-center text-sm text-ink-400">{{ t('reporting.maintenance.empty') }}</div>
            </template>
        </DataTable>
    </AppLayout>
</template>
