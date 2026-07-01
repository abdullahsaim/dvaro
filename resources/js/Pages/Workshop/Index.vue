<script setup>
// Tenant-admin workshop overview (read-only) — design-system pass.
// Status tabs + vehicle filter, paginated DataTable.
import { computed } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import DataTable from '@/Components/UI/DataTable.vue';
import StatusBadge from '@/Components/UI/StatusBadge.vue';
import EmptyState from '@/Components/UI/EmptyState.vue';
import Select from '@/Components/UI/Select.vue';
import { WrenchScrewdriverIcon } from '@heroicons/vue/24/outline';
import { useCurrency } from '@/composables/useCurrency';

const props = defineProps({
    logs: { type: Object, required: true },
    statuses: { type: Array, required: true },
    statusCounts: { type: Object, required: true },
    activeStatus: { type: String, default: null },
    activeVehicleId: { type: Number, default: null },
    vehicles: { type: Array, required: true },
});

const { t } = useI18n();
const { formatAUD } = useCurrency();
const page = usePage();
const base = computed(() => `/app/${page.props.tenant.slug}/workshop`);

// maintenance status enum → generic StatusBadge variant.
const statusVariants = {
    pending: 'neutral',
    in_progress: 'info',
    completed: 'success',
    waiting_for_parts: 'warning',
    re_inspection_required: 'danger',
};

function filter(params) {
    router.get(base.value, {
        status: 'status' in params ? params.status : props.activeStatus,
        vehicle_id: 'vehicle_id' in params ? params.vehicle_id : props.activeVehicleId,
    }, { preserveState: true, replace: true });
}

function onVehicleChange(e) {
    const value = e.target.value;
    filter({ vehicle_id: value === '' ? null : Number(value) });
}

function vehicleLabel(log) {
    const v = log.vehicle;
    return v ? `${v.make} ${v.model} · ${v.registration_number}` : '—';
}
</script>

<template>
    <AppLayout>
        <Head :title="t('workshop.title')" />

        <PageHeader :title="t('workshop.title')" />

        <!-- Filters -->
        <div class="mb-4 flex flex-wrap items-center gap-4">
            <div class="flex flex-wrap gap-2">
                <button
                    type="button"
                    class="rounded-full px-3 py-1 text-sm transition-colors"
                    :class="activeStatus === null
                        ? 'bg-ink-950 text-white dark:bg-ink-50 dark:text-ink-950'
                        : 'bg-ink-100 text-ink-600 hover:bg-ink-200 dark:bg-ink-800 dark:text-ink-300 dark:hover:bg-ink-700'"
                    @click="filter({ status: null })"
                >
                    {{ t('common.all') }} ({{ statusCounts.all }})
                </button>
                <button
                    v-for="s in statuses"
                    :key="s"
                    type="button"
                    class="rounded-full px-3 py-1 text-sm transition-colors"
                    :class="activeStatus === s
                        ? 'bg-ink-950 text-white dark:bg-ink-50 dark:text-ink-950'
                        : 'bg-ink-100 text-ink-600 hover:bg-ink-200 dark:bg-ink-800 dark:text-ink-300 dark:hover:bg-ink-700'"
                    @click="filter({ status: s })"
                >
                    {{ t(`workshop.statuses.${s}`) }} ({{ statusCounts[s] }})
                </button>
            </div>

            <Select :model-value="activeVehicleId ?? ''" class="ml-auto w-64" @change="onVehicleChange">
                <option value="">{{ t('workshop.all_vehicles') }}</option>
                <option v-for="v in vehicles" :key="v.id" :value="v.id">
                    {{ v.make }} {{ v.model }} · {{ v.registration_number }}
                </option>
            </Select>
        </div>

        <DataTable :columns="6" :empty="!logs.data.length" :pagination="logs">
            <template #head>
                <th class="px-4 py-3">{{ t('workshop.fields.title') }}</th>
                <th class="px-4 py-3">{{ t('workshop.vehicle') }}</th>
                <th class="px-4 py-3">{{ t('workshop.mechanic') }}</th>
                <th class="px-4 py-3">{{ t('fleet.fields.status') }}</th>
                <th class="px-4 py-3 text-right">{{ t('workshop.total') }}</th>
                <th class="px-4 py-3 text-right">{{ t('common.actions') }}</th>
            </template>

            <tr v-for="log in logs.data" :key="log.id" class="text-ink-700 dark:text-ink-200">
                <td class="px-4 py-3 font-medium text-ink-900 dark:text-ink-50">{{ log.title }}</td>
                <td class="px-4 py-3 text-ink-500">{{ vehicleLabel(log) }}</td>
                <td class="px-4 py-3 text-ink-500">{{ log.mechanic?.name ?? '—' }}</td>
                <td class="px-4 py-3">
                    <StatusBadge :variant="statusVariants[log.status]" :label="t(`workshop.statuses.${log.status}`)" />
                </td>
                <td class="px-4 py-3 text-right tabular-nums">{{ formatAUD(log.total_cost) }}</td>
                <td class="px-4 py-3 text-right">
                    <Link :href="`${base}/${log.id}`" class="text-sm font-medium text-ink-900 hover:underline dark:text-ink-100">
                        {{ t('workshop.view') }}
                    </Link>
                </td>
            </tr>

            <template #empty>
                <EmptyState :title="t('workshop.empty')">
                    <template #icon><WrenchScrewdriverIcon class="h-6 w-6" /></template>
                </EmptyState>
            </template>
        </DataTable>
    </AppLayout>
</template>
