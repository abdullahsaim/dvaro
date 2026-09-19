<script setup>
// Plate search results (0 or 2+ matches — a single match redirects straight
// to the vehicle page server-side). Mobile-first list.
import { computed } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { TruckIcon } from '@heroicons/vue/24/outline';
import MechanicLayout from '@/Layouts/MechanicLayout.vue';
import StatusBadge from '@/Components/UI/StatusBadge.vue';
import EmptyState from '@/Components/UI/EmptyState.vue';
import PlateSearch from '@/Components/Workshop/PlateSearch.vue';

defineProps({
    plate: { type: String, default: '' },
    results: { type: Array, required: true },
    tooShort: { type: Boolean, default: false },
});

const { t } = useI18n();
const page = usePage();
const base = computed(() => `/mechanic/${page.props.tenant.slug}`);

const statusVariants = {
    available: 'success',
    rented: 'info',
    maintenance: 'warning',
    suspended: 'neutral',
    accident: 'danger',
    reserved: 'neutral',
};
</script>

<template>
    <MechanicLayout>
        <Head :title="t('workshop.plate_search.title')" />

        <div class="flex items-center justify-between gap-3">
            <h1 class="text-2xl font-semibold tracking-tight text-ink-900 dark:text-ink-50">{{ t('workshop.plate_search.title') }}</h1>
            <Link :href="`${base}/dashboard`" class="text-sm text-ink-500 hover:underline">{{ t('common.back') }}</Link>
        </div>

        <div class="mt-4">
            <PlateSearch :initial="plate" />
        </div>

        <p v-if="tooShort" class="mt-6 text-sm text-ink-500">{{ t('workshop.plate_search.too_short') }}</p>

        <template v-else>
            <template v-if="results.length">
                <p class="mt-6 text-xs font-medium uppercase tracking-wide text-ink-500">
                    {{ t('workshop.plate_search.results', { count: results.length, plate }) }}
                </p>
                <ul class="mt-3 space-y-2">
                    <li v-for="vehicle in results" :key="vehicle.id">
                        <Link
                            :href="`${base}/vehicles/${vehicle.id}`"
                            class="flex items-center justify-between gap-3 rounded-card border border-ink-200 bg-white p-4 shadow-subtle transition-colors duration-150 hover:border-ink-400 dark:border-ink-800 dark:bg-ink-900 dark:hover:border-ink-600"
                        >
                            <div class="min-w-0">
                                <p class="font-semibold uppercase tracking-wide text-ink-900 dark:text-ink-50">{{ vehicle.registration_number }}</p>
                                <p class="truncate text-sm text-ink-500">{{ vehicle.make }} {{ vehicle.model }} · {{ vehicle.year }}</p>
                            </div>
                            <StatusBadge :variant="statusVariants[vehicle.status]" :label="t(`fleet.statuses.${vehicle.status}`)" />
                        </Link>
                    </li>
                </ul>
            </template>

            <div v-else class="mt-8">
                <EmptyState :title="t('workshop.plate_search.no_results', { plate })" :message="t('workshop.plate_search.no_results_hint')">
                    <template #icon><TruckIcon class="h-6 w-6" /></template>
                </EmptyState>
            </div>
        </template>
    </MechanicLayout>
</template>
