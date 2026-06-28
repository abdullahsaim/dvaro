<script setup>
// Super admin tenant list — search, status filter, suspend/activate. FUNCTIONAL.
import { ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import SuperAdminLayout from '@/Layouts/SuperAdminLayout.vue';

const props = defineProps({
    tenants: { type: Object, required: true },
    statuses: { type: Array, required: true },
    statusCounts: { type: Object, required: true },
    activeStatus: { type: String, default: null },
    search: { type: String, default: '' },
});

const { t } = useI18n();
const searchTerm = ref(props.search);

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

        <div class="py-10">
            <h1 class="text-2xl font-semibold">{{ t('superadmin.tenants.title') }}</h1>

            <!-- Filters -->
            <div class="mt-6 flex flex-wrap items-center gap-4">
                <div class="flex flex-wrap gap-2">
                    <button
                        type="button"
                        class="rounded-full px-3 py-1 text-sm"
                        :class="activeStatus === null ? 'bg-slate-800 text-white dark:bg-slate-200 dark:text-slate-900' : 'bg-slate-100 dark:bg-slate-800'"
                        @click="filter({ status: null })"
                    >
                        {{ t('common.all') }} ({{ statusCounts.all }})
                    </button>
                    <button
                        v-for="s in statuses"
                        :key="s"
                        type="button"
                        class="rounded-full px-3 py-1 text-sm"
                        :class="activeStatus === s ? 'bg-slate-800 text-white dark:bg-slate-200 dark:text-slate-900' : 'bg-slate-100 dark:bg-slate-800'"
                        @click="filter({ status: s })"
                    >
                        {{ t(`superadmin.statuses.${s}`) }} ({{ statusCounts[s] }})
                    </button>
                </div>

                <form class="ml-auto" @submit.prevent="filter({ search: searchTerm })">
                    <input
                        v-model="searchTerm"
                        type="search"
                        :placeholder="t('superadmin.tenants.search_placeholder')"
                        class="w-64 rounded border border-slate-300 px-3 py-1.5 text-sm dark:border-slate-700 dark:bg-slate-900"
                    />
                </form>
            </div>

            <!-- Table -->
            <p v-if="!tenants.data.length" class="mt-8 text-sm text-slate-500 dark:text-slate-400">
                {{ t('superadmin.tenants.empty') }}
            </p>
            <div v-else class="mt-6 overflow-hidden rounded border border-slate-200 dark:border-slate-800">
                <table class="w-full text-left text-sm">
                    <thead class="bg-slate-50 text-slate-500 dark:bg-slate-900 dark:text-slate-400">
                        <tr>
                            <th class="px-4 py-2 font-medium">{{ t('superadmin.tenants.title') }}</th>
                            <th class="px-4 py-2 font-medium">{{ t('superadmin.tenants.plan') }}</th>
                            <th class="px-4 py-2 font-medium">{{ t('fleet.fields.status') }}</th>
                            <th class="px-4 py-2 font-medium">{{ t('superadmin.tenants.signed_up') }}</th>
                            <th class="px-4 py-2 text-right">{{ t('common.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="tenant in tenants.data" :key="tenant.id" class="border-t border-slate-100 dark:border-slate-800">
                            <td class="px-4 py-2">
                                <p class="font-medium">{{ tenant.name }}</p>
                                <p class="text-xs text-slate-400">{{ tenant.slug }}</p>
                            </td>
                            <td class="px-4 py-2 text-slate-500 dark:text-slate-400">{{ tenant.plan_name ?? '—' }}</td>
                            <td class="px-4 py-2">
                                <span class="rounded-full bg-slate-100 px-2.5 py-0.5 text-xs dark:bg-slate-800">
                                    {{ t(`superadmin.statuses.${tenant.status}`) }}
                                </span>
                            </td>
                            <td class="px-4 py-2 text-slate-500 dark:text-slate-400">{{ formatDate(tenant.created_at) }}</td>
                            <td class="px-4 py-2">
                                <div class="flex justify-end gap-3">
                                    <Link :href="`/superadmin/tenants/${tenant.slug}`" class="text-indigo-600 hover:underline dark:text-indigo-400">
                                        {{ t('superadmin.tenants.view') }}
                                    </Link>
                                    <button
                                        v-if="tenant.status !== 'suspended'"
                                        type="button"
                                        class="text-red-600 hover:underline"
                                        @click="suspend(tenant)"
                                    >
                                        {{ t('superadmin.tenants.suspend') }}
                                    </button>
                                    <button
                                        v-else
                                        type="button"
                                        class="text-green-600 hover:underline"
                                        @click="activate(tenant)"
                                    >
                                        {{ t('superadmin.tenants.activate') }}
                                    </button>
                                    <button
                                        type="button"
                                        class="text-slate-600 hover:underline dark:text-slate-300"
                                        @click="impersonate(tenant)"
                                    >
                                        {{ t('superadmin.tenants.impersonate') }}
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div v-if="tenants.links" class="mt-4 flex flex-wrap gap-1">
                <component
                    :is="link.url ? 'button' : 'span'"
                    v-for="(link, i) in tenants.links"
                    :key="i"
                    type="button"
                    class="rounded px-3 py-1 text-sm"
                    :class="link.active ? 'bg-slate-800 text-white dark:bg-slate-200 dark:text-slate-900' : 'text-slate-500 dark:text-slate-400'"
                    :disabled="!link.url"
                    @click="link.url && router.get(link.url, {}, { preserveState: true })"
                    v-html="link.label"
                />
            </div>
        </div>
    </SuperAdminLayout>
</template>
