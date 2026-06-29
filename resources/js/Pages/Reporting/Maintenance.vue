<script setup>
// Maintenance costs report — labour + parts per vehicle. FUNCTIONAL ONLY.
import { Head } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import AppLayout from '@/Layouts/AppLayout.vue';
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

        <div class="py-10">
            <h1 class="text-2xl font-semibold">{{ t('reporting.nav.maintenance') }}</h1>
            <ReportNav active="maintenance" class="mt-6" />

            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-slate-200 text-left text-slate-500 dark:border-slate-800">
                        <th class="py-2">{{ t('reporting.maintenance.vehicle') }}</th>
                        <th class="py-2 text-right">{{ t('reporting.maintenance.jobs') }}</th>
                        <th class="py-2 text-right">{{ t('reporting.maintenance.labour') }}</th>
                        <th class="py-2 text-right">{{ t('reporting.maintenance.parts') }}</th>
                        <th class="py-2 text-right">{{ t('reporting.maintenance.total') }}</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="row in rows" :key="row.vehicle" class="border-b border-slate-100 dark:border-slate-800/60">
                        <td class="py-2">{{ row.vehicle }}</td>
                        <td class="py-2 text-right">{{ row.jobs }}</td>
                        <td class="py-2 text-right">{{ formatAUD(row.labour_cost) }}</td>
                        <td class="py-2 text-right">{{ formatAUD(row.parts_cost) }}</td>
                        <td class="py-2 text-right font-semibold">{{ formatAUD(row.total_cost) }}</td>
                    </tr>
                    <tr v-if="!rows.length">
                        <td colspan="5" class="py-3 text-slate-400">{{ t('reporting.maintenance.empty') }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </AppLayout>
</template>
