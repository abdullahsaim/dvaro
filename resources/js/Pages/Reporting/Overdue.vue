<script setup>
// Overdue payments report. No date range (always "as of now"). FUNCTIONAL ONLY.
import { Head } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import AppLayout from '@/Layouts/AppLayout.vue';
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

        <div class="py-10">
            <h1 class="text-2xl font-semibold">{{ t('reporting.nav.overdue') }}</h1>
            <ReportNav active="overdue" class="mt-6" />
            <ReportToolbar report-type="overdue" :exports="exports" :with-date-range="false" />

            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-slate-200 text-left text-slate-500 dark:border-slate-800">
                        <th class="py-2">{{ t('reporting.overdue.customer') }}</th>
                        <th class="py-2 text-right">{{ t('reporting.overdue.total') }}</th>
                        <th class="py-2 text-right">{{ t('reporting.overdue.outstanding') }}</th>
                        <th class="py-2 text-right">{{ t('reporting.overdue.days_overdue') }}</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="row in rows" :key="row.invoice_id" class="border-b border-slate-100 dark:border-slate-800/60">
                        <td class="py-2">{{ row.customer }}</td>
                        <td class="py-2 text-right">{{ formatAUD(row.total) }}</td>
                        <td class="py-2 text-right font-semibold text-red-600 dark:text-red-400">{{ formatAUD(row.outstanding) }}</td>
                        <td class="py-2 text-right">{{ row.days_overdue }}</td>
                    </tr>
                    <tr v-if="!rows.length">
                        <td colspan="4" class="py-3 text-slate-400">{{ t('reporting.overdue.empty') }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </AppLayout>
</template>
