<script setup>
// Super admin tenant list — search, status filter, suspend/activate. Design-system pass.
import { ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import SuperAdminLayout from '@/Layouts/SuperAdminLayout.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import DataTable from '@/Components/UI/DataTable.vue';
import StatusBadge from '@/Components/UI/StatusBadge.vue';
import Input from '@/Components/UI/Input.vue';

const props = defineProps({
    tenants: { type: Object, required: true },
    statuses: { type: Array, required: true },
    statusCounts: { type: Object, required: true },
    activeStatus: { type: String, default: null },
    search: { type: String, default: '' },
});

const { t } = useI18n();
const searchTerm = ref(props.search);

const statusVariants = {
    active: 'success',
    trialing: 'info',
    trial: 'info',
    suspended: 'danger',
    cancelled: 'neutral',
    past_due: 'warning',
};

function filter(params) {
    router.get('/superadmin/tenants', {
        status: 'status' in params ? params.status : props.activeStatus,
        search: 'search' in params ? params.search : searchTerm.value,
    }, { preserveState: true, replace: true });
}

function suspend(tenant) {
    if (confirm(t('superadmin.tenants.confirm_suspend'))) {
        router.post(`/superadmin/tenants/${tenant.slug}/suspend`, {}, { preserveScroll: true });
    }
}

function activate(tenant) {
    router.post(`/superadmin/tenants/${tenant.slug}/activate`, {}, { preserveScroll: true });
}

function impersonate(tenant) {
    if (confirm(t('superadmin.tenants.confirm_impersonate'))) {
        router.post(`/superadmin/tenants/${tenant.slug}/impersonate`);
    }
}

function formatDate(value) {
    return value ? new Date(value).toLocaleDateString('en-AU') : '—';
}
</script>

<template>
    <SuperAdminLayout>
        <Head :title="t('superadmin.tenants.title')" />

        <PageHeader :title="t('superadmin.tenants.title')" />

        <!-- Filters -->
        <div class="mb-4 flex flex-wrap items-center gap-4">
            <div class="flex flex-wrap gap-2">
                <button
                    type="button"
                    class="rounded-full px-3 py-1 text-sm transition-colors"
                    :class="activeStatus === null
                        ? 'bg-ink-950 text-white dark:bg-ink-50 dark:text-ink-950'
                        : 'bg-ink-100 text-ink-600 hover:bg-ink-200 dark:bg-ink-800 dark:text-ink-300 dark:hover:bg-ink-700'"
                    @click="filter({ status: null })"
                >
                    {{ t('common.all') }} ({{ statusCounts.all }})
                </button>
                <button
                    v-for="s in statuses"
                    :key="s"
                    type="button"
                    class="rounded-full px-3 py-1 text-sm transition-colors"
                    :class="activeStatus === s
                        ? 'bg-ink-950 text-white dark:bg-ink-50 dark:text-ink-950'
                        : 'bg-ink-100 text-ink-600 hover:bg-ink-200 dark:bg-ink-800 dark:text-ink-300 dark:hover:bg-ink-700'"
                    @click="filter({ status: s })"
                >
                    {{ t(`superadmin.statuses.${s}`) }} ({{ statusCounts[s] }})
                </button>
            </div>

            <form class="ml-auto" @submit.prevent="filter({ search: searchTerm })">
                <Input v-model="searchTerm" type="search" :placeholder="t('superadmin.tenants.search_placeholder')" class="w-64" />
            </form>
        </div>

        <DataTable :columns="5" :empty="!tenants.data.length" :pagination="tenants">
            <template #head>
                <th class="px-4 py-2">{{ t('superadmin.tenants.title') }}</th>
                <th class="px-4 py-2">{{ t('superadmin.tenants.plan') }}</th>
                <th class="px-4 py-2">{{ t('fleet.fields.status') }}</th>
                <th class="px-4 py-2">{{ t('superadmin.tenants.signed_up') }}</th>
                <th class="px-4 py-2 text-right">{{ t('common.actions') }}</th>
            </template>
            <tr v-for="tenant in tenants.data" :key="tenant.id" class="text-ink-700 dark:text-ink-200">
                <td class="px-4 py-2">
                    <p class="font-medium text-ink-900 dark:text-ink-50">{{ tenant.name }}</p>
                    <p class="text-xs text-ink-400">{{ tenant.slug }}</p>
                </td>
                <td class="px-4 py-2 text-ink-500">{{ tenant.plan_name ?? '—' }}</td>
                <td class="px-4 py-2">
                    <StatusBadge :variant="statusVariants[tenant.status]" :label="t(`superadmin.statuses.${tenant.status}`)" />
                </td>
                <td class="px-4 py-2 text-ink-500">{{ formatDate(tenant.created_at) }}</td>
                <td class="px-4 py-2">
                    <div class="flex justify-end gap-3">
                        <Link :href="`/superadmin/tenants/${tenant.slug}`" class="text-sm font-medium text-ink-900 hover:underline dark:text-ink-100">
                            {{ t('superadmin.tenants.view') }}
                        </Link>
                        <button v-if="tenant.status !== 'suspended'" type="button" class="text-sm text-danger-600 hover:underline dark:text-danger-500" @click="suspend(tenant)">
                            {{ t('superadmin.tenants.suspend') }}
                        </button>
                        <button v-else type="button" class="text-sm text-success-600 hover:underline dark:text-success-500" @click="activate(tenant)">
                            {{ t('superadmin.tenants.activate') }}
                        </button>
                        <button type="button" class="text-sm text-ink-500 hover:underline" @click="impersonate(tenant)">
                            {{ t('superadmin.tenants.impersonate') }}
                        </button>
                    </div>
                </td>
            </tr>
            <template #empty>
                <div class="px-4 py-6 text-center text-sm text-ink-400">{{ t('superadmin.tenants.empty') }}</div>
            </template>
        </DataTable>
    </SuperAdminLayout>
</template>
