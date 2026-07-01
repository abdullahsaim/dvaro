<script setup>
// Customer list — design-system pass. Search (name/phone) + status filter
// (all/active/blacklisted), paginated DataTable, blacklist StatusBadge.
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
import { UsersIcon } from '@heroicons/vue/24/outline';
import { useCurrency } from '@/composables/useCurrency';

const props = defineProps({
    customers: { type: Object, required: true }, // Laravel paginator payload
    filters: { type: Object, required: true }, // { search, blacklisted }
});

const { t } = useI18n();
const { formatAUD } = useCurrency();
const page = usePage();

const slug = computed(() => page.props.tenant.slug);
const base = computed(() => `/app/${slug.value}/customers`);

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
</script>

<template>
    <AppLayout>
        <Head :title="t('customer.title')" />

        <PageHeader :title="t('customer.title')">
            <template #actions>
                <Button @click="router.visit(`${base}/create`)">{{ t('customer.add_customer') }}</Button>
            </template>
        </PageHeader>

        <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
            <!-- Status filter tabs -->
            <div class="flex flex-wrap gap-2">
                <button
                    v-for="tab in tabs"
                    :key="tab.key"
                    type="button"
                    class="rounded-full px-3 py-1 text-sm transition-colors"
                    :class="activeTab === tab.key
                        ? 'bg-ink-950 text-white dark:bg-ink-50 dark:text-ink-950'
                        : 'bg-ink-100 text-ink-600 hover:bg-ink-200 dark:bg-ink-800 dark:text-ink-300 dark:hover:bg-ink-700'"
                    @click="filterBy(tab.key)"
                >
                    {{ t(`customer.${tab.label}`) }}
                </button>
            </div>

            <!-- Search -->
            <form class="flex items-end gap-2" @submit.prevent="submitSearch">
                <Input v-model="search" type="search" :placeholder="t('customer.search_placeholder')" class="w-64" />
                <Button type="submit" variant="secondary">{{ t('common.search') }}</Button>
            </form>
        </div>

        <DataTable :columns="6" :empty="customers.data.length === 0" :pagination="customers">
            <template #head>
                <th class="px-4 py-3">{{ t('customer.fields.name') }}</th>
                <th class="px-4 py-3">{{ t('customer.fields.email') }}</th>
                <th class="px-4 py-3">{{ t('customer.fields.phone') }}</th>
                <th class="px-4 py-3 text-right">{{ t('customer.outstanding_balance') }}</th>
                <th class="px-4 py-3">{{ t('customer.status') }}</th>
                <th class="px-4 py-3 text-right">{{ t('common.actions') }}</th>
            </template>

            <tr
                v-for="customer in customers.data"
                :key="customer.id"
                class="text-ink-700 dark:text-ink-200"
                :class="customer.is_blacklisted ? 'bg-danger-50/60 dark:bg-danger-900/10' : ''"
            >
                <td class="px-4 py-3 font-medium text-ink-900 dark:text-ink-50">{{ customer.name }}</td>
                <td class="px-4 py-3">{{ customer.email }}</td>
                <td class="px-4 py-3">{{ customer.phone }}</td>
                <td class="px-4 py-3 text-right tabular-nums">
                    <span v-if="customer.outstanding_balance > 0" class="font-medium text-warning-700 dark:text-warning-500">
                        {{ formatAUD(customer.outstanding_balance) }}
                    </span>
                    <span v-else class="text-ink-400">{{ t('customer.balance_clear') }}</span>
                </td>
                <td class="px-4 py-3">
                    <StatusBadge
                        :variant="customer.is_blacklisted ? 'danger' : 'success'"
                        :label="customer.is_blacklisted ? t('customer.blacklisted_badge') : t('customer.active_badge')"
                    />
                </td>
                <td class="px-4 py-3">
                    <div class="flex justify-end gap-3">
                        <Link :href="`${base}/${customer.id}`" class="text-sm font-medium text-ink-900 hover:underline dark:text-ink-100">
                            {{ t('customer.customer_details') }}
                        </Link>
                        <Link :href="`${base}/${customer.id}/edit`" class="text-sm text-ink-500 hover:underline">
                            {{ t('common.edit') }}
                        </Link>
                        <button type="button" class="text-sm text-danger-600 hover:underline dark:text-danger-500" @click="destroy(customer)">
                            {{ t('common.delete') }}
                        </button>
                    </div>
                </td>
            </tr>

            <template #empty>
                <EmptyState :title="t('customer.empty')" :message="t('customer.add_customer')">
                    <template #icon><UsersIcon class="h-6 w-6" /></template>
                    <template #action>
                        <Button @click="router.visit(`${base}/create`)">{{ t('customer.add_customer') }}</Button>
                    </template>
                </EmptyState>
            </template>
        </DataTable>
    </AppLayout>
</template>
