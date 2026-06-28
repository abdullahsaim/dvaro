<script setup>
// Tenant-admin workshop overview (read-only). Status tabs + vehicle filter.
// FUNCTIONAL ONLY — design pass later.
import { computed } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import AppLayout from '@/Layouts/AppLayout.vue';
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

        <div class="py-10">
            <h1 class="text-2xl font-semibold">{{ t('workshop.title') }}</h1>

            <!-- Filters -->
            <div class="mt-6 flex flex-wrap items-center gap-4">
                <div class="flex flex-wrap gap-2">
                    <button
                        type="button"
                        class="rounded-full px-3 py-1 text-sm"
                        :class="activeStatus === null ? 'bg-slate-800 text-white dark:bg-slate-200 dark:text-slate-900' : 'bg-slate-100 dark:bg-slate-800'"
                        @click="filter({ status: null })"
                    >
                        {{ t('common.all') }} ({{ statusCounts.all }})
                    </button>
                    <button
                        v-for="s in statuses"
                        :key="s"
                        type="button"
                        class="rounded-full px-3 py-1 text-sm"
                        :class="activeStatus === s ? 'bg-slate-800 text-white dark:bg-slate-200 dark:text-slate-900' : 'bg-slate-100 dark:bg-slate-800'"
                        @click="filter({ status: s })"
                    >
                        {{ t(`workshop.statuses.${s}`) }} ({{ statusCounts[s] }})
                    </button>
                </div>

                <label class="ml-auto block">
                    <span class="sr-only">{{ t('workshop.filter_vehicle') }}</span>
                    <select
                        class="rounded border border-slate-300 px-3 py-1.5 text-sm dark:border-slate-700 dark:bg-slate-900"
                        :value="activeVehicleId ?? ''"
                        @change="onVehicleChange"
                    >
                        <option value="">{{ t('workshop.all_vehicles') }}</option>
                        <option v-for="v in vehicles" :key="v.id" :value="v.id">
                            {{ v.make }} {{ v.model }} · {{ v.registration_number }}
                        </option>
                    </select>
                </label>
            </div>

            <!-- Table -->
            <p v-if="!logs.data.length" class="mt-8 text-sm text-slate-500 dark:text-slate-400">
                {{ t('workshop.empty') }}
            </p>
            <div v-else class="mt-6 overflow-hidden rounded border border-slate-200 dark:border-slate-800">
                <table class="w-full text-left text-sm">
                    <thead class="bg-slate-50 text-slate-500 dark:bg-slate-900 dark:text-slate-400">
                        <tr>
                            <th class="px-4 py-2 font-medium">{{ t('workshop.fields.title') }}</th>
                            <th class="px-4 py-2 font-medium">{{ t('workshop.vehicle') }}</th>
                            <th class="px-4 py-2 font-medium">{{ t('workshop.mechanic') }}</th>
                            <th class="px-4 py-2 font-medium">{{ t('fleet.fields.status') }}</th>
                            <th class="px-4 py-2 text-right font-medium">{{ t('workshop.total') }}</th>
                            <th class="px-4 py-2"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="log in logs.data" :key="log.id" class="border-t border-slate-100 dark:border-slate-800">
                            <td class="px-4 py-2">{{ log.title }}</td>
                            <td class="px-4 py-2 text-slate-500 dark:text-slate-400">{{ vehicleLabel(log) }}</td>
                            <td class="px-4 py-2 text-slate-500 dark:text-slate-400">{{ log.mechanic?.name ?? '—' }}</td>
                            <td class="px-4 py-2">
                                <span class="rounded-full bg-slate-100 px-2.5 py-0.5 text-xs dark:bg-slate-800">
                                    {{ t(`workshop.statuses.${log.status}`) }}
                                </span>
                            </td>
                            <td class="px-4 py-2 text-right">{{ formatAUD(log.total_cost) }}</td>
                            <td class="px-4 py-2 text-right">
                                <Link :href="`${base}/${log.id}`" class="text-indigo-600 hover:underline dark:text-indigo-400">
                                    {{ t('workshop.view') }}
                                </Link>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div v-if="logs.links" class="mt-4 flex flex-wrap gap-1">
                <component
                    :is="link.url ? 'button' : 'span'"
                    v-for="(link, i) in logs.links"
                    :key="i"
                    type="button"
                    class="rounded px-3 py-1 text-sm"
                    :class="link.active ? 'bg-slate-800 text-white dark:bg-slate-200 dark:text-slate-900' : 'text-slate-500 dark:text-slate-400'"
                    :disabled="!link.url"
                    @click="link.url && router.get(link.url, {}, { preserveState: true })"
                    v-html="link.label"
                />
            </div>
        </div>
    </AppLayout>
</template>
