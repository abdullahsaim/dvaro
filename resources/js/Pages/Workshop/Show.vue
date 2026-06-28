<script setup>
// Tenant-admin service log detail (read-only). FUNCTIONAL ONLY — design later.
import { computed } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import AppLayout from '@/Layouts/AppLayout.vue';
import { useCurrency } from '@/composables/useCurrency';

const props = defineProps({
    log: { type: Object, required: true },
    statuses: { type: Array, required: true },
});

const { t } = useI18n();
const { formatAUD } = useCurrency();
const page = usePage();
const base = computed(() => `/app/${page.props.tenant.slug}/workshop`);

const vehicle = computed(() => props.log.vehicle);
const partsTotal = computed(() =>
    (props.log.parts ?? []).reduce((sum, p) => sum + (p.total_cost || 0), 0),
);
</script>

<template>
    <AppLayout>
        <Head :title="t('workshop.log_details')" />

        <div class="py-10">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <h1 class="text-2xl font-semibold">{{ log.title }}</h1>
                    <span class="rounded-full bg-slate-100 px-2.5 py-0.5 text-xs dark:bg-slate-800">
                        {{ t(`workshop.statuses.${log.status}`) }}
                    </span>
                </div>
                <Link :href="base" class="text-sm text-slate-500 hover:underline dark:text-slate-400">
                    {{ t('common.back') }}
                </Link>
            </div>

            <p v-if="log.description" class="mt-3 max-w-2xl text-slate-600 dark:text-slate-300">
                {{ log.description }}
            </p>

            <!-- Summary -->
            <dl class="mt-6 grid max-w-2xl grid-cols-1 gap-px overflow-hidden rounded border border-slate-200 bg-slate-200 sm:grid-cols-2 dark:border-slate-800 dark:bg-slate-800">
                <div class="bg-white p-4 dark:bg-slate-950">
                    <dt class="text-sm text-slate-500 dark:text-slate-400">{{ t('workshop.vehicle') }}</dt>
                    <dd class="mt-1 font-medium">
                        <Link v-if="vehicle" :href="`/app/${page.props.tenant.slug}/fleet/${vehicle.id}`"
                            class="text-indigo-600 hover:underline dark:text-indigo-400">
                            {{ vehicle.make }} {{ vehicle.model }} · {{ vehicle.registration_number }}
                        </Link>
                        <span v-else>—</span>
                    </dd>
                </div>
                <div class="bg-white p-4 dark:bg-slate-950">
                    <dt class="text-sm text-slate-500 dark:text-slate-400">{{ t('workshop.mechanic') }}</dt>
                    <dd class="mt-1 font-medium">{{ log.mechanic?.name ?? '—' }}</dd>
                </div>
                <div class="bg-white p-4 dark:bg-slate-950">
                    <dt class="text-sm text-slate-500 dark:text-slate-400">{{ t('workshop.odometer') }}</dt>
                    <dd class="mt-1 font-medium">{{ log.odometer_reading ?? '—' }}</dd>
                </div>
                <div class="bg-white p-4 dark:bg-slate-950">
                    <dt class="text-sm text-slate-500 dark:text-slate-400">{{ t('workshop.started_at') }}</dt>
                    <dd class="mt-1 font-medium">{{ log.started_at ?? '—' }}</dd>
                </div>
                <div class="bg-white p-4 dark:bg-slate-950">
                    <dt class="text-sm text-slate-500 dark:text-slate-400">{{ t('workshop.completed_at') }}</dt>
                    <dd class="mt-1 font-medium">{{ log.completed_at ?? '—' }}</dd>
                </div>
            </dl>

            <!-- Parts -->
            <h2 class="mt-8 text-sm font-medium uppercase tracking-wide text-slate-500 dark:text-slate-400">
                {{ t('workshop.parts') }}
            </h2>
            <p v-if="!log.parts?.length" class="mt-2 text-sm text-slate-500 dark:text-slate-400">
                {{ t('workshop.no_parts') }}
            </p>
            <table v-else class="mt-2 w-full max-w-2xl text-left text-sm">
                <thead class="text-slate-500 dark:text-slate-400">
                    <tr>
                        <th class="py-1 font-medium">{{ t('workshop.fields.part_name') }}</th>
                        <th class="py-1 text-right font-medium">{{ t('workshop.fields.quantity') }}</th>
                        <th class="py-1 text-right font-medium">{{ t('workshop.fields.unit_cost') }}</th>
                        <th class="py-1 text-right font-medium">{{ t('workshop.total') }}</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="part in log.parts" :key="part.id" class="border-t border-slate-100 dark:border-slate-800">
                        <td class="py-1">{{ part.name }}</td>
                        <td class="py-1 text-right">{{ part.quantity }}</td>
                        <td class="py-1 text-right">{{ formatAUD(part.unit_cost) }}</td>
                        <td class="py-1 text-right">{{ formatAUD(part.total_cost) }}</td>
                    </tr>
                </tbody>
            </table>

            <!-- Costs -->
            <dl class="mt-6 max-w-xs space-y-1 text-sm">
                <div class="flex justify-between">
                    <dt class="text-slate-500 dark:text-slate-400">{{ t('workshop.labour') }}</dt>
                    <dd class="font-medium">{{ formatAUD(log.labour_cost) }}</dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-slate-500 dark:text-slate-400">{{ t('workshop.parts_total') }}</dt>
                    <dd class="font-medium">{{ formatAUD(partsTotal) }}</dd>
                </div>
                <div class="flex justify-between border-t border-slate-200 pt-1 dark:border-slate-800">
                    <dt class="font-medium">{{ t('workshop.total') }}</dt>
                    <dd class="font-semibold">{{ formatAUD(log.total_cost) }}</dd>
                </div>
            </dl>
        </div>
    </AppLayout>
</template>
