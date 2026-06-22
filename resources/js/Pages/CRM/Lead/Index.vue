<script setup>
// Lead list. FUNCTIONAL ONLY — design pass comes in a later session.
// Status filter tabs (with counts) + name/phone search, paginated table.
import { computed, ref } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    leads: { type: Object, required: true }, // Laravel paginator payload
    filters: { type: Object, required: true }, // { status, search }
    counts: { type: Object, required: true }, // { all, new, contacted, ... }
});

const { t } = useI18n();
const page = usePage();

const slug = computed(() => page.props.tenant.slug);
const base = computed(() => `/app/${slug.value}/leads`);
const flash = computed(() => page.props.flash?.success);

const search = ref(props.filters.search ?? '');

// '' = all; the rest map 1:1 to ?status= values.
const tabs = ['', 'new', 'contacted', 'converted', 'expired', 'rejected'];
const activeTab = computed(() => props.filters.status ?? '');

const statusClasses = {
    new: 'bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-300',
    contacted: 'bg-indigo-100 text-indigo-800 dark:bg-indigo-900/40 dark:text-indigo-300',
    converted: 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300',
    expired: 'bg-slate-200 text-slate-700 dark:bg-slate-700 dark:text-slate-300',
    rejected: 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-300',
};

function query(overrides = {}) {
    const params = {
        search: search.value || undefined,
        status: props.filters.status || undefined,
        ...overrides,
    };
    router.get(base.value, params, { preserveState: true, preserveScroll: true, replace: true });
}

function submitSearch() {
    query({ search: search.value || undefined });
}

function filterBy(key) {
    query({ status: key || undefined });
}

function convert(lead) {
    router.post(`${base.value}/${lead.id}/convert`, {}, { preserveScroll: true });
}

function expire(lead) {
    if (!window.confirm(t('crm.confirm_expire'))) return;
    router.post(`${base.value}/${lead.id}/expire`, {}, { preserveScroll: true });
}

function destroy(lead) {
    if (!window.confirm(t('crm.confirm_delete'))) return;
    router.delete(`${base.value}/${lead.id}`, { preserveScroll: true });
}

// Convertible mirrors Lead::isConvertible(): submitted, not converted/expired.
function isConvertible(lead) {
    return ['new', 'contacted'].includes(lead.status) && !!lead.submitted_at && !lead.expires_manually;
}
</script>

<template>
    <AppLayout>
        <Head :title="t('crm.title')" />

        <div class="py-10">
            <div class="flex items-center justify-between">
                <h1 class="text-2xl font-semibold">{{ t('crm.title') }}</h1>
                <Link
                    :href="`${base}/create`"
                    class="rounded bg-slate-800 px-3 py-2 text-sm text-white dark:bg-slate-200 dark:text-slate-900"
                >
                    {{ t('crm.add_lead') }}
                </Link>
            </div>

            <p
                v-if="flash"
                class="mt-4 rounded border border-green-200 bg-green-50 px-3 py-2 text-sm text-green-800 dark:border-green-900 dark:bg-green-900/30 dark:text-green-300"
            >
                {{ flash }}
            </p>

            <div class="mt-6 flex flex-wrap items-center justify-between gap-3">
                <!-- Status filter tabs with counts -->
                <div class="flex flex-wrap gap-2">
                    <button
                        v-for="tab in tabs"
                        :key="tab"
                        type="button"
                        class="rounded-full px-3 py-1 text-sm transition-colors"
                        :class="activeTab === tab
                            ? 'bg-slate-800 text-white dark:bg-slate-200 dark:text-slate-900'
                            : 'bg-slate-100 text-slate-600 hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700'"
                        @click="filterBy(tab)"
                    >
                        {{ tab === '' ? t('common.all') : t(`crm.statuses.${tab}`) }}
                        <span class="ml-1 opacity-60">{{ tab === '' ? counts.all : counts[tab] }}</span>
                    </button>
                </div>

                <!-- Search -->
                <form class="flex gap-2" @submit.prevent="submitSearch">
                    <input
                        v-model="search"
                        type="search"
                        :placeholder="t('crm.search_placeholder')"
                        class="w-64 rounded border border-slate-300 px-3 py-1.5 text-sm dark:border-slate-700 dark:bg-slate-900"
                    />
                    <button
                        type="submit"
                        class="rounded bg-slate-100 px-3 py-1.5 text-sm text-slate-700 hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700"
                    >
                        {{ t('common.search') }}
                    </button>
                </form>
            </div>

            <!-- Table -->
            <div class="mt-6 overflow-x-auto rounded border border-slate-200 dark:border-slate-800">
                <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-800">
                    <thead class="text-left text-slate-500 dark:text-slate-400">
                        <tr>
                            <th class="px-4 py-3 font-medium">{{ t('crm.fields.name') }}</th>
                            <th class="px-4 py-3 font-medium">{{ t('crm.fields.email') }}</th>
                            <th class="px-4 py-3 font-medium">{{ t('crm.fields.phone') }}</th>
                            <th class="px-4 py-3 font-medium">{{ t('crm.status_label') }}</th>
                            <th class="px-4 py-3 font-medium">{{ t('crm.submitted') }}</th>
                            <th class="px-4 py-3 text-right font-medium">{{ t('common.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        <tr v-if="leads.data.length === 0">
                            <td colspan="6" class="px-4 py-8 text-center text-slate-500 dark:text-slate-400">
                                {{ t('crm.empty') }}
                            </td>
                        </tr>
                        <tr v-for="lead in leads.data" :key="lead.id">
                            <td class="px-4 py-3 font-medium">{{ lead.name }}</td>
                            <td class="px-4 py-3">{{ lead.email }}</td>
                            <td class="px-4 py-3">{{ lead.phone }}</td>
                            <td class="px-4 py-3">
                                <span
                                    class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium"
                                    :class="statusClasses[lead.status]"
                                >
                                    {{ t(`crm.statuses.${lead.status}`) }}
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                <span v-if="lead.submitted_at" class="text-green-600 dark:text-green-400">✓</span>
                                <span v-else class="text-slate-400">{{ t('common.none') }}</span>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex justify-end gap-3">
                                    <Link :href="`${base}/${lead.id}`" class="text-indigo-600 hover:underline dark:text-indigo-400">
                                        {{ t('crm.lead_details') }}
                                    </Link>
                                    <button
                                        v-if="isConvertible(lead)"
                                        type="button"
                                        class="text-green-600 hover:underline dark:text-green-400"
                                        @click="convert(lead)"
                                    >
                                        {{ t('crm.convert') }}
                                    </button>
                                    <button
                                        v-if="!lead.expires_manually && lead.status !== 'converted'"
                                        type="button"
                                        class="text-amber-600 hover:underline dark:text-amber-400"
                                        @click="expire(lead)"
                                    >
                                        {{ t('crm.expire') }}
                                    </button>
                                    <button type="button" class="text-red-600 hover:underline dark:text-red-400" @click="destroy(lead)">
                                        {{ t('common.delete') }}
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div v-if="leads.links.length > 3" class="mt-4 flex flex-wrap gap-1">
                <component
                    :is="link.url ? Link : 'span'"
                    v-for="(link, i) in leads.links"
                    :key="i"
                    :href="link.url"
                    class="rounded px-3 py-1 text-sm"
                    :class="[
                        link.active ? 'bg-slate-800 text-white dark:bg-slate-200 dark:text-slate-900' : 'text-slate-600 dark:text-slate-300',
                        !link.url && 'cursor-default opacity-40',
                    ]"
                    v-html="link.label"
                />
            </div>
        </div>
    </AppLayout>
</template>
