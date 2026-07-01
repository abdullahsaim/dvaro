<script setup>
// Invoice list — design-system pass. Status tabs + customer-name search,
// paginated DataTable with right-aligned amounts.
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
import { BanknotesIcon } from '@heroicons/vue/24/outline';
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

const search = ref(props.filters.search ?? '');
const activeStatus = computed(() => props.filters.status ?? '');

// invoice status enum → generic StatusBadge variant.
const statusVariants = {
    draft: 'neutral',
    sent: 'info',
    paid: 'success',
    overdue: 'danger',
    cancelled: 'neutral',
};

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

        <PageHeader :title="t('invoice.title')" />

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
                    {{ t('invoice.filter_all') }} ({{ counts.all }})
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
                    {{ t(`invoice.statuses.${s}`) }} ({{ counts[s] ?? 0 }})
                </button>
            </div>

            <form class="flex items-end gap-2" @submit.prevent="submitSearch">
                <Input v-model="search" type="search" :placeholder="t('invoice.search_placeholder')" class="w-56" />
                <Button type="submit" variant="secondary">{{ t('common.search') }}</Button>
            </form>
        </div>

        <DataTable :columns="9" :empty="invoices.data.length === 0" :pagination="invoices">
            <template #head>
                <th class="px-4 py-3">#</th>
                <th class="px-4 py-3">{{ t('invoice.fields.customer') }}</th>
                <th class="px-4 py-3">{{ t('invoice.fields.type') }}</th>
                <th class="px-4 py-3">{{ t('invoice.fields.due_date') }}</th>
                <th class="px-4 py-3 text-right">{{ t('invoice.total') }}</th>
                <th class="px-4 py-3 text-right">{{ t('invoice.paid') }}</th>
                <th class="px-4 py-3 text-right">{{ t('invoice.outstanding') }}</th>
                <th class="px-4 py-3">{{ t('invoice.fields.status') }}</th>
                <th class="px-4 py-3 text-right">{{ t('common.actions') }}</th>
            </template>

            <tr v-for="invoice in invoices.data" :key="invoice.id" class="text-ink-700 dark:text-ink-200">
                <td class="px-4 py-3 font-medium text-ink-900 dark:text-ink-50">#{{ invoice.id }}</td>
                <td class="px-4 py-3">{{ invoice.customer?.name ?? t('common.none') }}</td>
                <td class="px-4 py-3">{{ t(`invoice.types.${invoice.type}`) }}</td>
                <td class="px-4 py-3">{{ toDate(invoice.due_date) }}</td>
                <td class="px-4 py-3 text-right tabular-nums">{{ formatAUD(invoice.total) }}</td>
                <td class="px-4 py-3 text-right tabular-nums">{{ formatAUD(invoice.paid_amount) }}</td>
                <td class="px-4 py-3 text-right font-medium tabular-nums">{{ formatAUD(outstanding(invoice)) }}</td>
                <td class="px-4 py-3">
                    <StatusBadge :variant="statusVariants[invoice.status]" :label="t(`invoice.statuses.${invoice.status}`)" />
                </td>
                <td class="px-4 py-3 text-right">
                    <Link :href="`${base}/${invoice.id}`" class="text-sm font-medium text-ink-900 hover:underline dark:text-ink-100">
                        {{ t('invoice.view') }}
                    </Link>
                </td>
            </tr>

            <template #empty>
                <EmptyState :title="t('invoice.empty')">
                    <template #icon><BanknotesIcon class="h-6 w-6" /></template>
                </EmptyState>
            </template>
        </DataTable>
    </AppLayout>
</template>
