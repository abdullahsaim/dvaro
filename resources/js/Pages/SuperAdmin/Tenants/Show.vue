<script setup>
// Super admin tenant detail — counts, subscription history, lifecycle actions.
// Design-system pass.
import { Head, Link, router } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import SuperAdminLayout from '@/Layouts/SuperAdminLayout.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import StatCard from '@/Components/UI/StatCard.vue';
import DataTable from '@/Components/UI/DataTable.vue';
import StatusBadge from '@/Components/UI/StatusBadge.vue';
import Button from '@/Components/UI/Button.vue';

const props = defineProps({
    tenant: { type: Object, required: true },
    counts: { type: Object, required: true },
    subscriptions: { type: Array, required: true },
});

const { t } = useI18n();

const statusVariants = {
    active: 'success',
    trialing: 'info',
    trial: 'info',
    suspended: 'danger',
    cancelled: 'neutral',
    past_due: 'warning',
};

function suspend() {
    if (confirm(t('superadmin.tenants.confirm_suspend'))) {
        router.post(`/superadmin/tenants/${props.tenant.slug}/suspend`, {}, { preserveScroll: true });
    }
}

function activate() {
    router.post(`/superadmin/tenants/${props.tenant.slug}/activate`, {}, { preserveScroll: true });
}

function impersonate() {
    if (confirm(t('superadmin.tenants.confirm_impersonate'))) {
        router.post(`/superadmin/tenants/${props.tenant.slug}/impersonate`);
    }
}

function formatDate(value) {
    return value ? new Date(value).toLocaleDateString('en-AU') : '—';
}
</script>

<template>
    <SuperAdminLayout>
        <Head :title="tenant.name" />

        <Link href="/superadmin/tenants" class="text-sm font-medium text-ink-500 hover:underline">
            ← {{ t('superadmin.tenants.title') }}
        </Link>

        <PageHeader class="mt-4">
            <template #title>
                <span class="flex flex-wrap items-center gap-3">
                    {{ tenant.name }}
                    <StatusBadge :variant="statusVariants[tenant.status]" :label="t(`superadmin.statuses.${tenant.status}`)" />
                </span>
            </template>
            <template #description>{{ tenant.slug }}</template>
            <template #actions>
                <Button v-if="tenant.status !== 'suspended'" variant="danger" @click="suspend">{{ t('superadmin.tenants.suspend') }}</Button>
                <Button v-else variant="primary" @click="activate">{{ t('superadmin.tenants.activate') }}</Button>
                <Button variant="secondary" @click="impersonate">{{ t('superadmin.tenants.impersonate') }}</Button>
            </template>
        </PageHeader>

        <!-- Counts -->
        <div class="grid grid-cols-3 gap-4">
            <StatCard :label="t('superadmin.tenants.counts.users')" :value="counts.users" />
            <StatCard :label="t('superadmin.tenants.counts.vehicles')" :value="counts.vehicles" />
            <StatCard :label="t('superadmin.tenants.counts.invoices')" :value="counts.invoices" />
        </div>

        <!-- Subscription history -->
        <h2 class="mt-10 text-lg font-semibold text-ink-900 dark:text-ink-50">{{ t('superadmin.tenants.subscription_history') }}</h2>
        <div class="mt-4">
            <DataTable :columns="4" :empty="!subscriptions.length">
                <template #head>
                    <th class="px-4 py-2">{{ t('superadmin.tenants.plan') }}</th>
                    <th class="px-4 py-2">{{ t('fleet.fields.status') }}</th>
                    <th class="px-4 py-2">{{ t('superadmin.subscriptions.billing_cycle') }}</th>
                    <th class="px-4 py-2">{{ t('superadmin.tenants.period') }}</th>
                </template>
                <tr v-for="sub in subscriptions" :key="sub.id" class="text-ink-700 dark:text-ink-200">
                    <td class="px-4 py-2 text-ink-900 dark:text-ink-50">{{ sub.plan_name ?? '—' }}</td>
                    <td class="px-4 py-2">
                        <StatusBadge :variant="statusVariants[sub.status]" :label="t(`superadmin.statuses.${sub.status}`)" />
                    </td>
                    <td class="px-4 py-2 text-ink-500">{{ sub.billing_cycle }}</td>
                    <td class="px-4 py-2 text-ink-500">
                        {{ formatDate(sub.current_period_start) }} – {{ formatDate(sub.current_period_end) }}
                    </td>
                </tr>
                <template #empty>
                    <div class="px-4 py-6 text-center text-sm text-ink-400">{{ t('superadmin.tenants.no_subscriptions') }}</div>
                </template>
            </DataTable>
        </div>
    </SuperAdminLayout>
</template>
