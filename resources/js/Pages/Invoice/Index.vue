<script setup>
// Invoice list. FUNCTIONAL ONLY — design pass later.
// Status tabs + customer-name search, paginated table with outstanding amount.
import { computed, ref } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import AppLayout from '@/Layouts/AppLayout.vue';
import { useCurrency } from '@/composables/useCurrency';

const props = defineProps({
    invoices: { type: Object, required: true }, // Laravel paginator payload
    filters: { type: Object, required: true }, // { status, search }
    statuses: { type: Array, required: true },
    counts: { type: Object, required: true },
});

const { t } = useI18n();
const page = usePage();
const { formatAUD } = useCurrency();

const base = computed(() => `/app/${page.props.tenant.slug}/invoices`);
const flash = computed(() => page.props.flash?.success);

const search = ref(props.filters.search ?? '');
const activeStatus = computed(() => props.filters.status ?? '');

const statusColors = {
    draft: 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300',
    sent: 'bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-300',
    paid: 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300',
    overdue: 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-300',
    cancelled: 'bg-slate-200 text-slate-600 dark:bg-slate-700 dark:text-slate-300',
};

function statusClass(status) {
    return statusColors[status] ?? statusColors.draft;
}

function query(overrides = {}) {
    const params = {
        status: props.filters.status || undefined,
        search: search.value || undefined,
        ...overrides,
    };
    router.get(base.value, params, { preserveState: true, preserveScroll: true, replace: true });
}

function filterStatus(key) {
    query({ status: key || undefined });
}

function submitSearch() {
    query({ search: search.value || undefined });
}

function toDate(value) {
    return value ? String(value).slice(0, 10) : t('common.none');
}

function outstanding(invoice) {
    return (Number(invoice.total) || 0) - (Number(invoice.paid_amount) || 0);
}
</script>

<template>
    <AppLayout>
        <Head :title="t('invoice.title')" />

        <div class="py-10">
            <div class="flex items-center justify-between">
                <h1 class="text-2xl font-semibold">{{ t('invoice.title') }}</h1>
            </div>

            <p
                v-if="flash"
                class="mt-4 rounded border border-green-200 bg-green-50 px-3 py-2 text-sm text-green-800 dark:border-green-900 dark:bg-green-900/30 dark:text-green-300"
            >
                {{ flash }}
            </p>

            <div class="mt-6 flex flex-wrap items-center justify-between gap-3">
                <!-- Status tabs -->
                <div class="flex flex-wrap gap-2">
                    <button
                        type="button"
                        class="rounded-full px-3 py-1 text-sm transition-colors"
                        :class="activeStatus === ''
                            ? 'bg-slate-800 text-white dark:bg-slate-200 dark:text-slate-900'
                            : 'bg-slate-100 text-slate-600 hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700'"
                        @click="filterStatus('')"
                    >
                        {{ t('invoice.filter_all') }} ({{ counts.all }})
                    </button>
                    <button
                        v-for="s in statuses"
                        :key="s"
                        type="button"
                        class="rounded-full px-3 py-1 text-sm transition-colors"
                        :class="activeStatus === s
                            ? 'bg-slate-800 text-white dark:bg-slate-200 dark:text-slate-900'
                            : 'bg-slate-100 text-slate-600 hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700'"
                        @click="filterStatus(s)"
                    >
                        {{ t(`invoice.statuses.${s}`) }} ({{ counts[s] ?? 0 }})
                    </button>
                </div>

                <form class="flex gap-2" @submit.prevent="submitSearch">
                    <input
                        v-model="search"
                        type="search"
                        :placeholder="t('invoice.search_placeholder')"
                        class="w-56 rounded border border-slate-300 px-3 py-1.5 text-sm dark:border-slate-700 dark:bg-slate-900"
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
                            <th class="px-4 py-3 font-medium">#</th>
                            <th class="px-4 py-3 font-medium">{{ t('invoice.fields.customer') }}</th>
                            <th class="px-4 py-3 font-medium">{{ t('invoice.fields.type') }}</th>
                            <th class="px-4 py-3 font-medium">{{ t('invoice.fields.due_date') }}</th>
                            <th class="px-4 py-3 text-right font-medium">{{ t('invoice.total') }}</th>
                            <th class="px-4 py-3 text-right font-medium">{{ t('invoice.paid') }}</th>
                            <th class="px-4 py-3 text-right font-medium">{{ t('invoice.outstanding') }}</th>
                            <th class="px-4 py-3 font-medium">{{ t('invoice.fields.status') }}</th>
                            <th class="px-4 py-3 text-right font-medium">{{ t('common.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        <tr v-if="invoices.data.length === 0">
                            <td colspan="9" class="px-4 py-8 text-center text-slate-500 dark:text-slate-400">
                                {{ t('invoice.empty') }}
                            </td>
                        </tr>
                        <tr v-for="invoice in invoices.data" :key="invoice.id">
                            <td class="px-4 py-3 font-medium">#{{ invoice.id }}</td>
                            <td class="px-4 py-3">{{ invoice.customer?.name ?? t('common.none') }}</td>
                            <td class="px-4 py-3">{{ t(`invoice.types.${invoice.type}`) }}</td>
                            <td class="px-4 py-3">{{ toDate(invoice.due_date) }}</td>
                            <td class="px-4 py-3 text-right">{{ formatAUD(invoice.total) }}</td>
                            <td class="px-4 py-3 text-right">{{ formatAUD(invoice.paid_amount) }}</td>
                            <td class="px-4 py-3 text-right font-medium">{{ formatAUD(outstanding(invoice)) }}</td>
                            <td class="px-4 py-3">
                                <span
                                    class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium"
                                    :class="statusClass(invoice.status)"
                                >
                                    {{ t(`invoice.statuses.${invoice.status}`) }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <Link :href="`${base}/${invoice.id}`" class="text-indigo-600 hover:underline dark:text-indigo-400">
                                    {{ t('invoice.view') }}
                                </Link>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div v-if="invoices.links.length > 3" class="mt-4 flex flex-wrap gap-1">
                <component
                    :is="link.url ? Link : 'span'"
                    v-for="(link, i) in invoices.links"
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
