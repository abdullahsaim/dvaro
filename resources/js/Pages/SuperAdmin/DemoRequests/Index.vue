<script setup>
// Super admin demo / contact request queue. Status tabs + table + mark-as-
// contacted action. Design-system pass.
import { Head, router } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import SuperAdminLayout from '@/Layouts/SuperAdminLayout.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import DataTable from '@/Components/UI/DataTable.vue';
import StatusBadge from '@/Components/UI/StatusBadge.vue';

const props = defineProps({
    requests: { type: Object, required: true },
    counts: { type: Object, default: () => ({}) },
    filterStatus: { type: String, default: null },
});

const { t } = useI18n();

const statuses = ['new', 'contacted', 'converted'];

const statusVariants = {
    new: 'info',
    contacted: 'warning',
    converted: 'success',
};

function filter(status) {
    router.get('/superadmin/demo-requests', { status }, { preserveState: true, replace: true });
}

function markContacted(id) {
    router.post(`/superadmin/demo-requests/${id}/contacted`, {}, { preserveScroll: true });
}

function formatDate(value) {
    return value ? new Date(value).toLocaleDateString('en-AU') : '—';
}
</script>

<template>
    <SuperAdminLayout>
        <Head :title="t('superadmin.demo_requests.title')" />

        <PageHeader :title="t('superadmin.demo_requests.title')" :description="t('superadmin.demo_requests.subtitle')" />

        <!-- Status tabs -->
        <div class="mb-4 flex flex-wrap gap-2">
            <button
                type="button"
                class="rounded-full px-3 py-1 text-sm transition-colors"
                :class="filterStatus === null
                    ? 'bg-ink-950 text-white dark:bg-ink-50 dark:text-ink-950'
                    : 'bg-ink-100 text-ink-600 hover:bg-ink-200 dark:bg-ink-800 dark:text-ink-300 dark:hover:bg-ink-700'"
                @click="filter(null)"
            >
                {{ t('superadmin.demo_requests.filter_all') }}
            </button>
            <button
                v-for="s in statuses"
                :key="s"
                type="button"
                class="rounded-full px-3 py-1 text-sm transition-colors"
                :class="filterStatus === s
                    ? 'bg-ink-950 text-white dark:bg-ink-50 dark:text-ink-950'
                    : 'bg-ink-100 text-ink-600 hover:bg-ink-200 dark:bg-ink-800 dark:text-ink-300 dark:hover:bg-ink-700'"
                @click="filter(s)"
            >
                {{ t(`superadmin.demo_requests.statuses.${s}`) }}
                <span class="ml-1 text-xs opacity-70">{{ counts[s] ?? 0 }}</span>
            </button>
        </div>

        <DataTable :columns="7" :empty="!requests.data.length" :pagination="requests">
            <template #head>
                <th class="px-4 py-2">{{ t('superadmin.demo_requests.company') }}</th>
                <th class="px-4 py-2">{{ t('superadmin.demo_requests.contact') }}</th>
                <th class="px-4 py-2">{{ t('superadmin.demo_requests.email') }}</th>
                <th class="px-4 py-2">{{ t('superadmin.demo_requests.message') }}</th>
                <th class="px-4 py-2">{{ t('superadmin.demo_requests.status') }}</th>
                <th class="px-4 py-2">{{ t('superadmin.demo_requests.received') }}</th>
                <th class="px-4 py-2 text-right">{{ t('common.actions') }}</th>
            </template>
            <tr v-for="req in requests.data" :key="req.id" class="align-top text-ink-700 dark:text-ink-200">
                <td class="px-4 py-2 font-medium text-ink-900 dark:text-ink-50">{{ req.company_name }}</td>
                <td class="px-4 py-2">
                    <div>{{ req.contact_name }}</div>
                    <div v-if="req.phone" class="text-xs text-ink-400">{{ req.phone }}</div>
                </td>
                <td class="px-4 py-2 text-ink-500">
                    <a :href="`mailto:${req.email}`" class="hover:underline">{{ req.email }}</a>
                </td>
                <td class="max-w-xs px-4 py-2 text-ink-500">
                    <span class="line-clamp-2">{{ req.message || '—' }}</span>
                </td>
                <td class="px-4 py-2">
                    <StatusBadge :variant="statusVariants[req.status]" :label="t(`superadmin.demo_requests.statuses.${req.status}`)" />
                </td>
                <td class="px-4 py-2 text-ink-500">{{ formatDate(req.created_at) }}</td>
                <td class="px-4 py-2 text-right">
                    <button
                        v-if="req.status === 'new'"
                        type="button"
                        class="text-sm font-medium text-ink-900 hover:underline dark:text-ink-100"
                        @click="markContacted(req.id)"
                    >
                        {{ t('superadmin.demo_requests.mark_contacted') }}
                    </button>
                </td>
            </tr>
            <template #empty>
                <div class="px-4 py-6 text-center text-sm text-ink-400">{{ t('superadmin.demo_requests.empty') }}</div>
            </template>
        </DataTable>
    </SuperAdminLayout>
</template>
