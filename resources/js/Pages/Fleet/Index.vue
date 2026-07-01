<script setup>
// Fleet list — design-system pass. Status filter tabs (All + 6 statuses with
// counts), paginated DataTable, generic StatusBadge, EmptyState.
import { computed } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import DataTable from '@/Components/UI/DataTable.vue';
import StatusBadge from '@/Components/UI/StatusBadge.vue';
import EmptyState from '@/Components/UI/EmptyState.vue';
import Button from '@/Components/UI/Button.vue';
import { TruckIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    vehicles: { type: Object, required: true }, // Laravel paginator payload
    statuses: { type: Array, required: true },
    statusCounts: { type: Object, required: true },
    activeStatus: { type: String, default: null },
});

const { t } = useI18n();
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

function filterBy(key) {
    router.get(base.value, key === 'all' ? {} : { status: key }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}

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

        <!-- Status filter tabs -->
        <div class="mb-4 flex flex-wrap gap-2">
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
        </div>

        <DataTable :columns="6" :empty="vehicles.data.length === 0" :pagination="vehicles">
            <template #head>
                <th class="px-4 py-3">{{ t('fleet.fields.registration_number') }}</th>
                <th class="px-4 py-3">{{ t('fleet.fields.make') }} / {{ t('fleet.fields.model') }}</th>
                <th class="px-4 py-3">{{ t('fleet.fields.year') }}</th>
                <th class="px-4 py-3">{{ t('fleet.fields.status') }}</th>
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
                <td class="px-4 py-3 text-right tabular-nums">{{ vehicle.daily_rate }}</td>
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
