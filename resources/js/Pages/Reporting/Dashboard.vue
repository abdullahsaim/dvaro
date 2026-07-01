<script setup>
// Reporting overview — KPI StatCards. Design-system pass.
import { Head } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import StatCard from '@/Components/UI/StatCard.vue';
import ReportNav from '@/Components/Reporting/ReportNav.vue';
import { useCurrency } from '@/composables/useCurrency';

const props = defineProps({
    stats: { type: Object, required: true },
});

const { t } = useI18n();
const { formatAUD } = useCurrency();
</script>

<template>
    <AppLayout>
        <Head :title="t('reporting.title')" />

        <PageHeader :title="t('reporting.title')" :description="t('reporting.subtitle')" />

        <ReportNav active="dashboard" />

        <dl class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <StatCard :label="t('reporting.kpi.revenue_this_month')" :value="formatAUD(props.stats.revenue_this_month)" />
            <StatCard :label="t('reporting.kpi.active_rentals')" :value="props.stats.active_rentals" />
            <StatCard :label="t('reporting.kpi.overdue')">
                {{ props.stats.overdue_count }}
                <template #description>{{ formatAUD(props.stats.overdue_total) }}</template>
            </StatCard>
            <StatCard :label="t('reporting.kpi.fleet_utilisation')" :value="`${props.stats.fleet_utilisation}%`" />
            <StatCard :label="t('reporting.kpi.vehicles_due_service')" :value="props.stats.vehicles_due_service" />
        </dl>
    </AppLayout>
</template>
