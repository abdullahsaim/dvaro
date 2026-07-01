<script setup>
// Customer growth report — new customers per month. Design-system pass.
import { Head } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import DataTable from '@/Components/UI/DataTable.vue';
import ReportNav from '@/Components/Reporting/ReportNav.vue';

defineProps({
    rows: { type: Array, default: () => [] },
    filters: { type: Object, default: null },
});

const { t } = useI18n();
</script>

<template>
    <AppLayout>
        <Head :title="t('reporting.nav.customers')" />

        <PageHeader :title="t('reporting.nav.customers')" />
        <ReportNav active="customers" />

        <DataTable :columns="2" :empty="!rows.length">
            <template #head>
                <th class="px-4 py-2">{{ t('reporting.customers.month') }}</th>
                <th class="px-4 py-2 text-right">{{ t('reporting.customers.new_customers') }}</th>
            </template>
            <tr v-for="row in rows" :key="row.month" class="text-ink-700 dark:text-ink-200">
                <td class="px-4 py-2">{{ row.month }}</td>
                <td class="px-4 py-2 text-right tabular-nums">{{ row.customers }}</td>
            </tr>
            <template #empty>
                <div class="px-4 py-6 text-center text-sm text-ink-400">{{ t('reporting.customers.empty') }}</div>
            </template>
        </DataTable>
    </AppLayout>
</template>
