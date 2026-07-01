<script setup>
// Agreement list — design-system pass. Status tabs + type filter + customer-name
// search, paginated DataTable, status + type badges.
import { computed, ref } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import DataTable from '@/Components/UI/DataTable.vue';
import StatusBadge from '@/Components/UI/StatusBadge.vue';
import EmptyState from '@/Components/UI/EmptyState.vue';
import Button from '@/Components/UI/Button.vue';
import Input from '@/Components/UI/Input.vue';
import Select from '@/Components/UI/Select.vue';
import { DocumentTextIcon } from '@heroicons/vue/24/outline';
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

const search = ref(props.filters.search ?? '');
const activeStatus = computed(() => props.filters.status ?? '');

// agreement status enum → generic StatusBadge variant.
const statusVariants = {
    draft: 'neutral',
    signed: 'info',
    active: 'success',
    completed: 'neutral',
    cancelled: 'danger',
};

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

        <PageHeader :title="t('agreement.title')">
            <template #actions>
                <Button @click="router.visit(`${base}/create`)">{{ t('agreement.add_agreement') }}</Button>
            </template>
        </PageHeader>

        <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
            <!-- Status tabs -->
            <div class="flex flex-wrap gap-2">
                <button
                    type="button"
                    class="rounded-full px-3 py-1 text-sm transition-colors"
                    :class="activeStatus === ''
                        ? 'bg-ink-950 text-white dark:bg-ink-50 dark:text-ink-950'
                        : 'bg-ink-100 text-ink-600 hover:bg-ink-200 dark:bg-ink-800 dark:text-ink-300 dark:hover:bg-ink-700'"
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
                        ? 'bg-ink-950 text-white dark:bg-ink-50 dark:text-ink-950'
                        : 'bg-ink-100 text-ink-600 hover:bg-ink-200 dark:bg-ink-800 dark:text-ink-300 dark:hover:bg-ink-700'"
                    @click="filterStatus(s)"
                >
                    {{ t(`agreement.statuses.${s}`) }} ({{ counts[s] ?? 0 }})
                </button>
            </div>

            <div class="flex flex-wrap items-end gap-2">
                <!-- Type filter -->
                <Select :model-value="filters.type ?? ''" class="w-44" @change="filterType">
                    <option value="">{{ t('agreement.all_types') }}</option>
                    <option v-for="ty in types" :key="ty" :value="ty">{{ t(`agreement.types.${ty}`) }}</option>
                </Select>

                <!-- Search -->
                <form class="flex items-end gap-2" @submit.prevent="submitSearch">
                    <Input v-model="search" type="search" :placeholder="t('agreement.search_placeholder')" class="w-56" />
                    <Button type="submit" variant="secondary">{{ t('common.search') }}</Button>
                </form>
            </div>
        </div>

        <DataTable :columns="8" :empty="agreements.data.length === 0" :pagination="agreements">
            <template #head>
                <th class="px-4 py-3">{{ t('agreement.fields.customer') }}</th>
                <th class="px-4 py-3">{{ t('agreement.fields.vehicle') }}</th>
                <th class="px-4 py-3">{{ t('agreement.fields.type') }}</th>
                <th class="px-4 py-3 text-right">{{ t('agreement.fields.rate') }}</th>
                <th class="px-4 py-3">{{ t('agreement.fields.version') }}</th>
                <th class="px-4 py-3">{{ t('agreement.fields.start_date') }}</th>
                <th class="px-4 py-3">{{ t('agreement.fields.status') }}</th>
                <th class="px-4 py-3 text-right">{{ t('common.actions') }}</th>
            </template>

            <tr v-for="agreement in agreements.data" :key="agreement.id" class="text-ink-700 dark:text-ink-200">
                <td class="px-4 py-3 font-medium text-ink-900 dark:text-ink-50">{{ agreement.customer?.name ?? t('common.none') }}</td>
                <td class="px-4 py-3">{{ agreement.vehicle?.registration_number ?? t('common.none') }}</td>
                <td class="px-4 py-3">
                    <StatusBadge variant="neutral" :label="t(`agreement.types.${agreement.type}`)" />
                </td>
                <td class="px-4 py-3 text-right tabular-nums">{{ formatAUD(agreement.rate) }}</td>
                <td class="px-4 py-3">v{{ agreement.version }}</td>
                <td class="px-4 py-3">{{ toDate(agreement.start_date) }}</td>
                <td class="px-4 py-3">
                    <StatusBadge :variant="statusVariants[agreement.status]" :label="t(`agreement.statuses.${agreement.status}`)" />
                </td>
                <td class="px-4 py-3 text-right">
                    <Link :href="`${base}/${agreement.id}`" class="text-sm font-medium text-ink-900 hover:underline dark:text-ink-100">
                        {{ t('agreement.agreement_details') }}
                    </Link>
                </td>
            </tr>

            <template #empty>
                <EmptyState :title="t('agreement.empty')" :message="t('agreement.add_agreement')">
                    <template #icon><DocumentTextIcon class="h-6 w-6" /></template>
                    <template #action>
                        <Button @click="router.visit(`${base}/create`)">{{ t('agreement.add_agreement') }}</Button>
                    </template>
                </EmptyState>
            </template>
        </DataTable>
    </AppLayout>
</template>
