<script setup>
// Fleet utilisation report — colour-coded utilisation %. FUNCTIONAL ONLY.
import { Head } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import AppLayout from '@/Layouts/AppLayout.vue';
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
    if (pct > 70) return 'text-green-600 dark:text-green-400';
    if (pct >= 40) return 'text-yellow-600 dark:text-yellow-400';
    return 'text-red-600 dark:text-red-400';
}
</script>

<template>
    <AppLayout>
        <Head :title="t('reporting.nav.fleet')" />

        <div class="py-10">
            <h1 class="text-2xl font-semibold">{{ t('reporting.nav.fleet') }}</h1>
            <ReportNav active="fleet" class="mt-6" />
            <ReportToolbar report-type="fleet" :filters="filters" :exports="exports" />

            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-slate-200 text-left text-slate-500 dark:border-slate-800">
                        <th class="py-2">{{ t('reporting.fleet.vehicle') }}</th>
                        <th class="py-2 text-right">{{ t('reporting.fleet.total_days') }}</th>
                        <th class="py-2 text-right">{{ t('reporting.fleet.days_rented') }}</th>
                        <th class="py-2 text-right">{{ t('reporting.fleet.utilisation') }}</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="row in rows" :key="row.vehicle" class="border-b border-slate-100 dark:border-slate-800/60">
                        <td class="py-2">{{ row.vehicle }}</td>
                        <td class="py-2 text-right">{{ row.total_days }}</td>
                        <td class="py-2 text-right">{{ row.days_rented }}</td>
                        <td class="py-2 text-right font-semibold" :class="utilisationClass(row.utilisation)">
                            {{ row.utilisation }}%
                        </td>
                    </tr>
                    <tr v-if="!rows.length">
                        <td colspan="4" class="py-3 text-slate-400">{{ t('reporting.fleet.empty') }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </AppLayout>
</template>
