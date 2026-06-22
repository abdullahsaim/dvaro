<script setup>
// Agreement list. FUNCTIONAL ONLY — design pass later.
// Status tabs + type filter + customer-name search, paginated table.
import { computed, ref } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import AppLayout from '@/Layouts/AppLayout.vue';
import { useCurrency } from '@/composables/useCurrency';

const props = defineProps({
    agreements: { type: Object, required: true }, // Laravel paginator payload
    filters: { type: Object, required: true }, // { status, type, search }
    statuses: { type: Array, required: true },
    types: { type: Array, required: true },
    counts: { type: Object, required: true },
});

const { t } = useI18n();
const page = usePage();
const { formatAUD } = useCurrency();

const slug = computed(() => page.props.tenant.slug);
const base = computed(() => `/app/${slug.value}/agreements`);
const flash = computed(() => page.props.flash?.success);

const search = ref(props.filters.search ?? '');
const activeStatus = computed(() => props.filters.status ?? '');

// Status badge colors keyed by status (agreement-specific).
const statusColors = {
    draft: 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300',
    signed: 'bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-300',
    active: 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300',
    completed: 'bg-purple-100 text-purple-800 dark:bg-purple-900/40 dark:text-purple-300',
    cancelled: 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-300',
};

function statusClass(status) {
    return statusColors[status] ?? statusColors.draft;
}

function query(overrides = {}) {
    const params = {
        status: props.filters.status || undefined,
        type: props.filters.type || undefined,
        search: search.value || undefined,
        ...overrides,
    };
    router.get(base.value, params, { preserveState: true, preserveScroll: true, replace: true });
}

function filterStatus(key) {
    query({ status: key || undefined });
}

function filterType(event) {
    query({ type: event.target.value || undefined });
}

function submitSearch() {
    query({ search: search.value || undefined });
}

function toDate(value) {
    return value ? String(value).slice(0, 10) : t('common.none');
}
</script>

<template>
    <AppLayout>
        <Head :title="t('agreement.title')" />

        <div class="py-10">
            <div class="flex items-center justify-between">
                <h1 class="text-2xl font-semibold">{{ t('agreement.title') }}</h1>
                <Link
                    :href="`${base}/create`"
                    class="rounded bg-slate-800 px-3 py-2 text-sm text-white dark:bg-slate-200 dark:text-slate-900"
                >
                    {{ t('agreement.add_agreement') }}
                </Link>
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
                        {{ t('agreement.filter_all') }} ({{ counts.all }})
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
                        {{ t(`agreement.statuses.${s}`) }} ({{ counts[s] ?? 0 }})
                    </button>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <!-- Type filter -->
                    <select
                        :value="filters.type ?? ''"
                        class="rounded border border-slate-300 px-3 py-1.5 text-sm dark:border-slate-700 dark:bg-slate-900"
                        @change="filterType"
                    >
                        <option value="">{{ t('agreement.all_types') }}</option>
                        <option v-for="ty in types" :key="ty" :value="ty">{{ t(`agreement.types.${ty}`) }}</option>
                    </select>

                    <!-- Search -->
                    <form class="flex gap-2" @submit.prevent="submitSearch">
                        <input
                            v-model="search"
                            type="search"
                            :placeholder="t('agreement.search_placeholder')"
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
            </div>

            <!-- Table -->
            <div class="mt-6 overflow-x-auto rounded border border-slate-200 dark:border-slate-800">
                <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-800">
                    <thead class="text-left text-slate-500 dark:text-slate-400">
                        <tr>
                            <th class="px-4 py-3 font-medium">{{ t('agreement.fields.customer') }}</th>
                            <th class="px-4 py-3 font-medium">{{ t('agreement.fields.vehicle') }}</th>
                            <th class="px-4 py-3 font-medium">{{ t('agreement.fields.type') }}</th>
                            <th class="px-4 py-3 font-medium">{{ t('agreement.fields.rate') }}</th>
                            <th class="px-4 py-3 font-medium">{{ t('agreement.fields.version') }}</th>
                            <th class="px-4 py-3 font-medium">{{ t('agreement.fields.start_date') }}</th>
                            <th class="px-4 py-3 font-medium">{{ t('agreement.fields.status') }}</th>
                            <th class="px-4 py-3 text-right font-medium">{{ t('common.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        <tr v-if="agreements.data.length === 0">
                            <td colspan="8" class="px-4 py-8 text-center text-slate-500 dark:text-slate-400">
                                {{ t('agreement.empty') }}
                            </td>
                        </tr>
                        <tr v-for="agreement in agreements.data" :key="agreement.id">
                            <td class="px-4 py-3 font-medium">{{ agreement.customer?.name ?? t('common.none') }}</td>
                            <td class="px-4 py-3">{{ agreement.vehicle?.registration_number ?? t('common.none') }}</td>
                            <td class="px-4 py-3">{{ t(`agreement.types.${agreement.type}`) }}</td>
                            <td class="px-4 py-3">{{ formatAUD(agreement.rate) }}</td>
                            <td class="px-4 py-3">v{{ agreement.version }}</td>
                            <td class="px-4 py-3">{{ toDate(agreement.start_date) }}</td>
                            <td class="px-4 py-3">
                                <span
                                    class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium"
                                    :class="statusClass(agreement.status)"
                                >
                                    {{ t(`agreement.statuses.${agreement.status}`) }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <Link :href="`${base}/${agreement.id}`" class="text-indigo-600 hover:underline dark:text-indigo-400">
                                    {{ t('agreement.agreement_details') }}
                                </Link>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div v-if="agreements.links.length > 3" class="mt-4 flex flex-wrap gap-1">
                <component
                    :is="link.url ? Link : 'span'"
                    v-for="(link, i) in agreements.links"
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
