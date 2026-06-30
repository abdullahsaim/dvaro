<script setup>
// Super admin demo / contact request queue. Status tabs + table + mark-as-
// contacted action. FUNCTIONAL ONLY — design pass later.
import { computed } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import SuperAdminLayout from '@/Layouts/SuperAdminLayout.vue';

const props = defineProps({
    requests: { type: Object, required: true },
    counts: { type: Object, default: () => ({}) },
    filterStatus: { type: String, default: null },
});

const { t } = useI18n();
const page = usePage();

const statuses = ['new', 'contacted', 'converted'];
const flash = computed(() => page.props.flash?.success ?? null);

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

        <div class="py-8">
            <div class="mb-6">
                <h1 class="text-2xl font-semibold tracking-tight">{{ t('superadmin.demo_requests.title') }}</h1>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ t('superadmin.demo_requests.subtitle') }}</p>
            </div>

            <p v-if="flash" class="mb-4 rounded-lg bg-emerald-50 px-4 py-3 text-sm text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300">
                {{ flash }}
            </p>

            <!-- Status tabs -->
            <div class="mb-6 flex flex-wrap gap-2">
                <button
                    type="button"
                    class="rounded-full px-3 py-1 text-sm"
                    :class="filterStatus === null ? 'bg-slate-800 text-white dark:bg-slate-200 dark:text-slate-900' : 'bg-slate-100 dark:bg-slate-800'"
                    @click="filter(null)"
                >
                    {{ t('superadmin.demo_requests.filter_all') }}
                </button>
                <button
                    v-for="s in statuses"
                    :key="s"
                    type="button"
                    class="rounded-full px-3 py-1 text-sm"
                    :class="filterStatus === s ? 'bg-slate-800 text-white dark:bg-slate-200 dark:text-slate-900' : 'bg-slate-100 dark:bg-slate-800'"
                    @click="filter(s)"
                >
                    {{ t(`superadmin.demo_requests.statuses.${s}`) }}
                    <span class="ml-1 text-xs opacity-70">{{ counts[s] ?? 0 }}</span>
                </button>
            </div>

            <p v-if="!requests.data.length" class="rounded-lg border border-slate-200 p-6 text-sm text-slate-500 dark:border-slate-800 dark:text-slate-400">
                {{ t('superadmin.demo_requests.empty') }}
            </p>

            <div v-else class="overflow-hidden rounded-xl border border-slate-200 dark:border-slate-800">
                <table class="w-full text-left text-sm">
                    <thead class="bg-slate-50 text-slate-500 dark:bg-slate-900 dark:text-slate-400">
                        <tr>
                            <th class="px-4 py-2 font-medium">{{ t('superadmin.demo_requests.company') }}</th>
                            <th class="px-4 py-2 font-medium">{{ t('superadmin.demo_requests.contact') }}</th>
                            <th class="px-4 py-2 font-medium">{{ t('superadmin.demo_requests.email') }}</th>
                            <th class="px-4 py-2 font-medium">{{ t('superadmin.demo_requests.message') }}</th>
                            <th class="px-4 py-2 font-medium">{{ t('superadmin.demo_requests.status') }}</th>
                            <th class="px-4 py-2 font-medium">{{ t('superadmin.demo_requests.received') }}</th>
                            <th class="px-4 py-2"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="req in requests.data" :key="req.id" class="border-t border-slate-100 align-top dark:border-slate-800">
                            <td class="px-4 py-2 font-medium">{{ req.company_name }}</td>
                            <td class="px-4 py-2">
                                <div>{{ req.contact_name }}</div>
                                <div v-if="req.phone" class="text-xs text-slate-400">{{ req.phone }}</div>
                            </td>
                            <td class="px-4 py-2 text-slate-500 dark:text-slate-400">
                                <a :href="`mailto:${req.email}`" class="hover:underline">{{ req.email }}</a>
                            </td>
                            <td class="max-w-xs px-4 py-2 text-slate-500 dark:text-slate-400">
                                <span class="line-clamp-2">{{ req.message || '—' }}</span>
                            </td>
                            <td class="px-4 py-2">
                                <span class="rounded-full bg-slate-100 px-2.5 py-0.5 text-xs dark:bg-slate-800">
                                    {{ t(`superadmin.demo_requests.statuses.${req.status}`) }}
                                </span>
                            </td>
                            <td class="px-4 py-2 text-slate-500 dark:text-slate-400">{{ formatDate(req.created_at) }}</td>
                            <td class="px-4 py-2 text-right">
                                <button
                                    v-if="req.status === 'new'"
                                    type="button"
                                    class="text-indigo-600 hover:underline dark:text-indigo-400"
                                    @click="markContacted(req.id)"
                                >
                                    {{ t('superadmin.demo_requests.mark_contacted') }}
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div v-if="requests.links" class="mt-4 flex flex-wrap gap-1">
                <component
                    :is="link.url ? 'button' : 'span'"
                    v-for="(link, i) in requests.links"
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
