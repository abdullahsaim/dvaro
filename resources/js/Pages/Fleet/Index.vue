<script setup>
// Fleet list. FUNCTIONAL ONLY — design pass comes in a later session.
// Status filter tabs (All + 6 statuses, with counts), paginated table.
import { computed } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import AppLayout from '@/Layouts/AppLayout.vue';
import StatusBadge from '@/Components/StatusBadge.vue';

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
const flash = computed(() => page.props.flash?.success);

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

        <div class="py-10">
            <div class="flex items-center justify-between">
                <h1 class="text-2xl font-semibold">{{ t('fleet.title') }}</h1>
                <Link
                    :href="`${base}/create`"
                    class="rounded bg-slate-800 px-3 py-2 text-sm text-white dark:bg-slate-200 dark:text-slate-900"
                >
                    {{ t('fleet.add_vehicle') }}
                </Link>
            </div>

            <p
                v-if="flash"
                class="mt-4 rounded border border-green-200 bg-green-50 px-3 py-2 text-sm text-green-800 dark:border-green-900 dark:bg-green-900/30 dark:text-green-300"
            >
                {{ flash }}
            </p>

            <!-- Status filter tabs -->
            <div class="mt-6 flex flex-wrap gap-2">
                <button
                    v-for="tab in tabs"
                    :key="tab.key"
                    type="button"
                    class="rounded-full px-3 py-1 text-sm transition-colors"
                    :class="isActive(tab.key)
                        ? 'bg-slate-800 text-white dark:bg-slate-200 dark:text-slate-900'
                        : 'bg-slate-100 text-slate-600 hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700'"
                    @click="filterBy(tab.key)"
                >
                    {{ tab.label }} ({{ tab.count }})
                </button>
            </div>

            <!-- Table -->
            <div class="mt-6 overflow-x-auto rounded border border-slate-200 dark:border-slate-800">
                <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-800">
                    <thead class="text-left text-slate-500 dark:text-slate-400">
                        <tr>
                            <th class="px-4 py-3 font-medium">{{ t('fleet.fields.registration_number') }}</th>
                            <th class="px-4 py-3 font-medium">{{ t('fleet.fields.make') }} / {{ t('fleet.fields.model') }}</th>
                            <th class="px-4 py-3 font-medium">{{ t('fleet.fields.year') }}</th>
                            <th class="px-4 py-3 font-medium">{{ t('fleet.fields.status') }}</th>
                            <th class="px-4 py-3 font-medium">{{ t('fleet.fields.daily_rate') }}</th>
                            <th class="px-4 py-3 text-right font-medium">{{ t('common.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        <tr v-if="vehicles.data.length === 0">
                            <td colspan="6" class="px-4 py-8 text-center text-slate-500 dark:text-slate-400">
                                {{ t('fleet.empty') }}
                            </td>
                        </tr>
                        <tr v-for="vehicle in vehicles.data" :key="vehicle.id">
                            <td class="px-4 py-3 font-medium">{{ vehicle.registration_number }}</td>
                            <td class="px-4 py-3">{{ vehicle.make }} {{ vehicle.model }}</td>
                            <td class="px-4 py-3">{{ vehicle.year }}</td>
                            <td class="px-4 py-3"><StatusBadge :status="vehicle.status" /></td>
                            <td class="px-4 py-3">{{ vehicle.daily_rate }}</td>
                            <td class="px-4 py-3">
                                <div class="flex justify-end gap-3">
                                    <Link :href="`${base}/${vehicle.id}`" class="text-indigo-600 hover:underline dark:text-indigo-400">
                                        {{ t('fleet.vehicle_details') }}
                                    </Link>
                                    <Link :href="`${base}/${vehicle.id}/edit`" class="text-slate-600 hover:underline dark:text-slate-300">
                                        {{ t('common.edit') }}
                                    </Link>
                                    <button type="button" class="text-red-600 hover:underline dark:text-red-400" @click="destroy(vehicle)">
                                        {{ t('common.delete') }}
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div v-if="vehicles.links.length > 3" class="mt-4 flex flex-wrap gap-1">
                <component
                    :is="link.url ? Link : 'span'"
                    v-for="(link, i) in vehicles.links"
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
