<script setup>
// Super admin dashboard — platform KPIs + recent signups. Design-system pass.
import { Head, Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import SuperAdminLayout from '@/Layouts/SuperAdminLayout.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import StatCard from '@/Components/UI/StatCard.vue';
import DataTable from '@/Components/UI/DataTable.vue';
import StatusBadge from '@/Components/UI/StatusBadge.vue';
import { useCurrency } from '@/composables/useCurrency';

const props = defineProps({
    stats: { type: Object, required: true },
    recentTenants: { type: Array, required: true },
});

const { t } = useI18n();
const { formatAUD } = useCurrency();

const statusVariants = {
    active: 'success',
    trialing: 'info',
    trial: 'info',
    suspended: 'danger',
    cancelled: 'neutral',
    past_due: 'warning',
};

const cards = [
    { key: 'total_tenants', label: 'superadmin.dashboard.total_tenants' },
    { key: 'active_tenants', label: 'superadmin.dashboard.active_tenants' },
    { key: 'trial_tenants', label: 'superadmin.dashboard.trial_tenants' },
    { key: 'new_this_month', label: 'superadmin.dashboard.new_this_month' },
];

function formatDate(value) {
    return value ? new Date(value).toLocaleDateString('en-AU') : '—';
}
</script>

<template>
    <SuperAdminLayout>
        <Head :title="t('superadmin.dashboard.title')" />

        <PageHeader :title="t('superadmin.dashboard.title')" />

        <!-- KPI cards -->
        <div class="grid grid-cols-2 gap-4 lg:grid-cols-5">
            <StatCard v-for="card in cards" :key="card.key" :label="t(card.label)" :value="stats[card.key]" />
            <StatCard :label="t('superadmin.dashboard.recurring_revenue')" :value="formatAUD(stats.recurring_revenue)">
                <template #description>{{ t('superadmin.dashboard.recurring_revenue_hint') }}</template>
            </StatCard>
        </div>

        <!-- Recent signups -->
        <h2 class="mt-10 text-lg font-semibold text-ink-900 dark:text-ink-50">{{ t('superadmin.dashboard.recent_signups') }}</h2>
        <div class="mt-4">
            <DataTable :columns="5" :empty="!recentTenants.length">
                <template #head>
                    <th class="px-4 py-2">{{ t('superadmin.tenants.title') }}</th>
                    <th class="px-4 py-2">{{ t('superadmin.tenants.plan') }}</th>
                    <th class="px-4 py-2">{{ t('fleet.fields.status') }}</th>
                    <th class="px-4 py-2">{{ t('superadmin.tenants.signed_up') }}</th>
                    <th class="px-4 py-2 text-right">{{ t('common.actions') }}</th>
                </template>
                <tr v-for="tenant in recentTenants" :key="tenant.id" class="text-ink-700 dark:text-ink-200">
                    <td class="px-4 py-2 font-medium text-ink-900 dark:text-ink-50">{{ tenant.name }}</td>
                    <td class="px-4 py-2 text-ink-500">{{ tenant.plan_name ?? '—' }}</td>
                    <td class="px-4 py-2">
                        <StatusBadge :variant="statusVariants[tenant.status]" :label="t(`superadmin.statuses.${tenant.status}`)" />
                    </td>
                    <td class="px-4 py-2 text-ink-500">{{ formatDate(tenant.created_at) }}</td>
                    <td class="px-4 py-2 text-right">
                        <Link :href="`/superadmin/tenants/${tenant.slug}`" class="text-sm font-medium text-ink-900 hover:underline dark:text-ink-100">
                            {{ t('superadmin.tenants.view') }}
                        </Link>
                    </td>
                </tr>
                <template #empty>
                    <div class="px-4 py-6 text-center text-sm text-ink-400">{{ t('superadmin.dashboard.no_tenants') }}</div>
                </template>
            </DataTable>
        </div>
    </SuperAdminLayout>
</template>
