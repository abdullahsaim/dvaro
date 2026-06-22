<script setup>
// Customer list. FUNCTIONAL ONLY — design pass comes in a later session.
// Search (name/phone) + status filter (all/active/blacklisted), paginated table.
import { computed, ref } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    customers: { type: Object, required: true }, // Laravel paginator payload
    filters: { type: Object, required: true }, // { search, blacklisted }
});

const { t } = useI18n();
const page = usePage();

const slug = computed(() => page.props.tenant.slug);
const base = computed(() => `/app/${slug.value}/customers`);
const flash = computed(() => page.props.flash?.success);

const search = ref(props.filters.search ?? '');

// Tabs map to the ?blacklisted query value ('' = all, 'false' = active, 'true').
const tabs = [
    { key: '', label: 'filter_all' },
    { key: 'false', label: 'filter_active' },
    { key: 'true', label: 'filter_blacklisted' },
];

const activeTab = computed(() => props.filters.blacklisted ?? '');

function query(overrides = {}) {
    const params = {
        search: search.value || undefined,
        blacklisted: props.filters.blacklisted || undefined,
        ...overrides,
    };
    router.get(base.value, params, { preserveState: true, preserveScroll: true, replace: true });
}

function submitSearch() {
    query({ search: search.value || undefined });
}

function filterBy(key) {
    query({ blacklisted: key || undefined });
}

function destroy(customer) {
    if (!window.confirm(t('customer.confirm_delete'))) return;
    router.delete(`${base.value}/${customer.id}`, { preserveScroll: true });
}

// cents → "$1,234.56"
function formatAud(cents) {
    return new Intl.NumberFormat('en-AU', { style: 'currency', currency: 'AUD' }).format(
        (cents ?? 0) / 100,
    );
}
</script>

<template>
    <AppLayout>
        <Head :title="t('customer.title')" />

        <div class="py-10">
            <div class="flex items-center justify-between">
                <h1 class="text-2xl font-semibold">{{ t('customer.title') }}</h1>
                <Link
                    :href="`${base}/create`"
                    class="rounded bg-slate-800 px-3 py-2 text-sm text-white dark:bg-slate-200 dark:text-slate-900"
                >
                    {{ t('customer.add_customer') }}
                </Link>
            </div>

            <p
                v-if="flash"
                class="mt-4 rounded border border-green-200 bg-green-50 px-3 py-2 text-sm text-green-800 dark:border-green-900 dark:bg-green-900/30 dark:text-green-300"
            >
                {{ flash }}
            </p>

            <div class="mt-6 flex flex-wrap items-center justify-between gap-3">
                <!-- Status filter tabs -->
                <div class="flex flex-wrap gap-2">
                    <button
                        v-for="tab in tabs"
                        :key="tab.key"
                        type="button"
                        class="rounded-full px-3 py-1 text-sm transition-colors"
                        :class="activeTab === tab.key
                            ? 'bg-slate-800 text-white dark:bg-slate-200 dark:text-slate-900'
                            : 'bg-slate-100 text-slate-600 hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700'"
                        @click="filterBy(tab.key)"
                    >
                        {{ t(`customer.${tab.label}`) }}
                    </button>
                </div>

                <!-- Search -->
                <form class="flex gap-2" @submit.prevent="submitSearch">
                    <input
                        v-model="search"
                        type="search"
                        :placeholder="t('customer.search_placeholder')"
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
                            <th class="px-4 py-3 font-medium">{{ t('customer.fields.name') }}</th>
                            <th class="px-4 py-3 font-medium">{{ t('customer.fields.email') }}</th>
                            <th class="px-4 py-3 font-medium">{{ t('customer.fields.phone') }}</th>
                            <th class="px-4 py-3 font-medium">{{ t('customer.outstanding_balance') }}</th>
                            <th class="px-4 py-3 font-medium">{{ t('customer.status') }}</th>
                            <th class="px-4 py-3 text-right font-medium">{{ t('common.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        <tr v-if="customers.data.length === 0">
                            <td colspan="6" class="px-4 py-8 text-center text-slate-500 dark:text-slate-400">
                                {{ t('customer.empty') }}
                            </td>
                        </tr>
                        <tr
                            v-for="customer in customers.data"
                            :key="customer.id"
                            :class="customer.is_blacklisted ? 'bg-red-50 dark:bg-red-900/20' : ''"
                        >
                            <td class="px-4 py-3 font-medium">{{ customer.name }}</td>
                            <td class="px-4 py-3">{{ customer.email }}</td>
                            <td class="px-4 py-3">{{ customer.phone }}</td>
                            <td class="px-4 py-3">
                                <span
                                    v-if="customer.outstanding_balance > 0"
                                    class="font-medium text-amber-700 dark:text-amber-400"
                                >
                                    {{ formatAud(customer.outstanding_balance) }}
                                </span>
                                <span v-else class="text-slate-400">{{ t('customer.balance_clear') }}</span>
                            </td>
                            <td class="px-4 py-3">
                                <span
                                    v-if="customer.is_blacklisted"
                                    class="inline-flex items-center rounded-full bg-red-100 px-2.5 py-0.5 text-xs font-medium text-red-800 dark:bg-red-900/40 dark:text-red-300"
                                >
                                    {{ t('customer.blacklisted_badge') }}
                                </span>
                                <span
                                    v-else
                                    class="inline-flex items-center rounded-full bg-green-100 px-2.5 py-0.5 text-xs font-medium text-green-800 dark:bg-green-900/40 dark:text-green-300"
                                >
                                    {{ t('customer.active_badge') }}
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex justify-end gap-3">
                                    <Link :href="`${base}/${customer.id}`" class="text-indigo-600 hover:underline dark:text-indigo-400">
                                        {{ t('customer.customer_details') }}
                                    </Link>
                                    <Link :href="`${base}/${customer.id}/edit`" class="text-slate-600 hover:underline dark:text-slate-300">
                                        {{ t('common.edit') }}
                                    </Link>
                                    <button type="button" class="text-red-600 hover:underline dark:text-red-400" @click="destroy(customer)">
                                        {{ t('common.delete') }}
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div v-if="customers.links.length > 3" class="mt-4 flex flex-wrap gap-1">
                <component
                    :is="link.url ? Link : 'span'"
                    v-for="(link, i) in customers.links"
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
