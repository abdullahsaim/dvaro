<script setup>
// Customer growth report — new customers per month. FUNCTIONAL ONLY.
import { Head } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import AppLayout from '@/Layouts/AppLayout.vue';
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

        <div class="py-10">
            <h1 class="text-2xl font-semibold">{{ t('reporting.nav.customers') }}</h1>
            <ReportNav active="customers" class="mt-6" />

            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-slate-200 text-left text-slate-500 dark:border-slate-800">
                        <th class="py-2">{{ t('reporting.customers.month') }}</th>
                        <th class="py-2 text-right">{{ t('reporting.customers.new_customers') }}</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="row in rows" :key="row.month" class="border-b border-slate-100 dark:border-slate-800/60">
                        <td class="py-2">{{ row.month }}</td>
                        <td class="py-2 text-right">{{ row.customers }}</td>
                    </tr>
                    <tr v-if="!rows.length">
                        <td colspan="2" class="py-3 text-slate-400">{{ t('reporting.customers.empty') }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </AppLayout>
</template>
