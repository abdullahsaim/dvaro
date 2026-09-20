<script setup>
// Fleet list — design-system pass. Status filter tabs (All + 6 statuses with
// counts), "Expiring soon" chip, sortable Rego Expiry / Next Service columns
// (badge state computed server-side), paginated DataTable, EmptyState.
import { computed } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import DataTable from '@/Components/UI/DataTable.vue';
import StatusBadge from '@/Components/UI/StatusBadge.vue';
import EmptyState from '@/Components/UI/EmptyState.vue';
import Button from '@/Components/UI/Button.vue';
import { TruckIcon, ChevronUpIcon, ChevronDownIcon } from '@heroicons/vue/24/outline';
import { useCurrency } from '@/composables/useCurrency';
import { useTenantFormat } from '@/composables/useTenantFormat';

const props = defineProps({
    vehicles: { type: Object, required: true }, // Laravel paginator payload
    statuses: { type: Array, required: true },
    statusCounts: { type: Object, required: true },
    activeStatus: { type: String, default: null },
    expiringCount: { type: Number, default: 0 },
    filters: { type: Object, default: () => ({ expiring: false, sort: null, direction: 'asc' }) },
});

const { t } = useI18n();
const { formatAUD } = useCurrency();
const numberFormat = new Intl.NumberFormat('en-AU');

// Service by km: "1,200 km to go" / "300 km over" (null when not tracked).
function serviceKmText(vehicle) {
    if (vehicle.next_service_km === null || vehicle.current_odometer === null) return null;
    const diff = vehicle.next_service_km - vehicle.current_odometer;
    return diff > 0
        ? t('fleet.schedule.km_to_go', { km: numberFormat.format(diff) })
        : t('fleet.schedule.km_over', { km: numberFormat.format(-diff) });
}
const page = usePage();

const slug = computed(() => page.props.tenant.slug);
const base = computed(() => `/app/${slug.value}/fleet`);

// vehicle status enum → generic StatusBadge variant.
const statusVariants = {
    available: 'success',
    rented: 'info',
    maintenance: 'warning',
    suspended: 'neutral',
    accident: 'danger',
    reserved: 'neutral',
};

// Tabs: 'all' first, then each status.
const tabs = computed(() => [
    { key: 'all', label: t('common.all'), count: props.statusCounts.all },
    ...props.statuses.map((s) => ({
        key: s,
        label: t(`fleet.statuses.${s}`),
        count: props.statusCounts[s] ?? 0,
    })),
]);

// Rebuild the query from the current filters + overrides, dropping empties.
function visit(overrides = {}) {
    const query = {
        status: props.activeStatus,
        expiring: props.filters.expiring ? 1 : null,
        sort: props.filters.sort,
        direction: props.filters.sort ? props.filters.direction : null,
        ...overrides,
    };
    Object.keys(query).forEach((k) => (query[k] === null || query[k] === undefined) && delete query[k]);

    router.get(base.value, query, { preserveState: true, preserveScroll: true, replace: true });
}

function filterBy(key) {
    visit({ status: key === 'all' ? null : key });
}

function toggleExpiring() {
    visit({ expiring: props.filters.expiring ? null : 1 });
}

// Click cycles: asc → desc → off.
function sortBy(column) {
    if (props.filters.sort !== column) return visit({ sort: column, direction: 'asc' });
    if (props.filters.direction === 'asc') return visit({ sort: column, direction: 'desc' });
    visit({ sort: null, direction: null });
}

// The company's date format (Settings → Regional). A due date is a date, not a
// moment, so it is rendered exactly as stored — never shifted by a timezone.
const { date: formatDate } = useTenantFormat();

// expiry state → StatusBadge variant (ok renders as plain text).
const expiryVariants = { overdue: 'danger', due_soon: 'warning' };

function isActive(key) {
    return key === 'all' ? !props.activeStatus : props.activeStatus === key;
}

function destroy(vehicle) {
    if (!window.confirm(t('common.confirm_delete'))) return;
    router.delete(`${base.value}/${vehicle.id}`, { preserveScroll: true });
}
</script>

<template>
    <AppLayout>
        <Head :title="t('fleet.title')" />

        <PageHeader :title="t('fleet.title')">
            <template #actions>
                <Button @click="router.visit(`${base}/create`)">{{ t('fleet.add_vehicle') }}</Button>
            </template>
        </PageHeader>

        <!-- Status filter tabs + expiring-soon chip -->
        <div class="mb-4 flex flex-wrap items-center gap-2">
            <button
                v-for="tab in tabs"
                :key="tab.key"
                type="button"
                class="rounded-full px-3 py-1 text-sm transition-colors"
                :class="isActive(tab.key)
                    ? 'bg-ink-950 text-white dark:bg-ink-50 dark:text-ink-950'
                    : 'bg-ink-100 text-ink-600 hover:bg-ink-200 dark:bg-ink-800 dark:text-ink-300 dark:hover:bg-ink-700'"
                @click="filterBy(tab.key)"
            >
                {{ tab.label }} ({{ tab.count }})
            </button>
            <span class="mx-1 hidden h-5 w-px bg-ink-200 sm:inline-block dark:bg-ink-800" aria-hidden="true" />
            <button
                type="button"
                class="rounded-full px-3 py-1 text-sm transition-colors"
                :class="filters.expiring
                    ? 'bg-warning-600 text-white'
                    : 'bg-warning-50 text-warning-700 hover:bg-warning-100 dark:bg-warning-900 dark:text-warning-500'"
                :aria-pressed="filters.expiring"
                @click="toggleExpiring"
            >
                {{ t('fleet.expiring_soon') }} ({{ expiringCount }})
            </button>
        </div>

        <DataTable :columns="8" :empty="vehicles.data.length === 0" :pagination="vehicles">
            <template #head>
                <th class="px-4 py-3">{{ t('fleet.fields.registration_number') }}</th>
                <th class="px-4 py-3">{{ t('fleet.fields.make') }} / {{ t('fleet.fields.model') }}</th>
                <th class="px-4 py-3">{{ t('fleet.fields.year') }}</th>
                <th class="px-4 py-3">{{ t('fleet.fields.status') }}</th>
                <th v-for="col in ['registration_expiry', 'next_service_due']" :key="col" class="px-4 py-3" :aria-sort="filters.sort === col ? (filters.direction === 'asc' ? 'ascending' : 'descending') : 'none'">
                    <button type="button" class="inline-flex items-center gap-1 whitespace-nowrap transition-colors hover:text-ink-900 dark:hover:text-ink-50" @click="sortBy(col)">
                        {{ t(`fleet.fields.${col}`) }}
                        <ChevronUpIcon v-if="filters.sort === col && filters.direction === 'asc'" class="h-3.5 w-3.5" />
                        <ChevronDownIcon v-else-if="filters.sort === col" class="h-3.5 w-3.5" />
                    </button>
                </th>
                <th class="px-4 py-3 text-right">{{ t('fleet.fields.daily_rate') }}</th>
                <th class="px-4 py-3 text-right">{{ t('common.actions') }}</th>
            </template>

            <tr v-for="vehicle in vehicles.data" :key="vehicle.id" class="text-ink-700 dark:text-ink-200">
                <td class="px-4 py-3 font-medium text-ink-900 dark:text-ink-50">{{ vehicle.registration_number }}</td>
                <td class="px-4 py-3">{{ vehicle.make }} {{ vehicle.model }}</td>
                <td class="px-4 py-3">{{ vehicle.year }}</td>
                <td class="px-4 py-3">
                    <StatusBadge :variant="statusVariants[vehicle.status]" :label="t(`fleet.statuses.${vehicle.status}`)" />
                </td>
                <td
                    v-for="cell in [
                        { key: 'registration_expiry', state: vehicle.registration_state },
                        { key: 'next_service_due', state: vehicle.service_state },
                    ]"
                    :key="cell.key"
                    class="whitespace-nowrap px-4 py-3 tabular-nums"
                >
                    <span v-if="!vehicle[cell.key] && !(cell.key === 'next_service_due' && serviceKmText(vehicle))" class="text-ink-400">—</span>
                    <span v-else class="inline-flex items-center gap-2">
                        <span>
                            {{ formatDate(vehicle[cell.key]) }}
                            <span
                                v-if="cell.key === 'next_service_due' && serviceKmText(vehicle)"
                                class="block text-xs text-ink-500"
                            >{{ serviceKmText(vehicle) }}</span>
                        </span>
                        <StatusBadge
                            v-if="expiryVariants[cell.state]"
                            :variant="expiryVariants[cell.state]"
                            :label="t(`fleet.expiry.${cell.state}`)"
                        />
                    </span>
                </td>
                <td class="px-4 py-3 text-right tabular-nums">{{ formatAUD(vehicle.daily_rate) }}</td>
                <td class="px-4 py-3">
                    <div class="flex justify-end gap-3">
                        <Link :href="`${base}/${vehicle.id}`" class="text-sm font-medium text-ink-900 hover:underline dark:text-ink-100">
                            {{ t('fleet.vehicle_details') }}
                        </Link>
                        <Link :href="`${base}/${vehicle.id}/edit`" class="text-sm text-ink-500 hover:underline">
                            {{ t('common.edit') }}
                        </Link>
                        <button type="button" class="text-sm text-danger-600 hover:underline dark:text-danger-500" @click="destroy(vehicle)">
                            {{ t('common.delete') }}
                        </button>
                    </div>
                </td>
            </tr>

            <template #empty>
                <EmptyState :title="t('fleet.empty')" :message="t('fleet.add_vehicle')">
                    <template #icon><TruckIcon class="h-6 w-6" /></template>
                    <template #action>
                        <Button @click="router.visit(`${base}/create`)">{{ t('fleet.add_vehicle') }}</Button>
                    </template>
                </EmptyState>
            </template>
        </DataTable>
    </AppLayout>
</template>
