<script setup>
// Tenant-admin service log detail (read-only) — design-system pass.
import { computed } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import DataTable from '@/Components/UI/DataTable.vue';
import StatusBadge from '@/Components/UI/StatusBadge.vue';
import Button from '@/Components/UI/Button.vue';
import { useCurrency } from '@/composables/useCurrency';

const props = defineProps({
    log: { type: Object, required: true },
    statuses: { type: Array, required: true },
});

const { t } = useI18n();
const { formatAUD } = useCurrency();
const page = usePage();
const base = computed(() => `/app/${page.props.tenant.slug}/workshop`);

const statusVariants = {
    pending: 'neutral',
    in_progress: 'info',
    completed: 'success',
    waiting_for_parts: 'warning',
    re_inspection_required: 'danger',
};

const vehicle = computed(() => props.log.vehicle);
const partsTotal = computed(() =>
    (props.log.parts ?? []).reduce((sum, p) => sum + (p.total_cost || 0), 0),
);
</script>

<template>
    <AppLayout>
        <Head :title="t('workshop.log_details')" />

        <PageHeader>
            <template #title>
                <span class="flex flex-wrap items-center gap-3">
                    {{ log.title }}
                    <StatusBadge :variant="statusVariants[log.status]" :label="t(`workshop.statuses.${log.status}`)" />
                </span>
            </template>
            <template #actions>
                <Button variant="ghost" @click="router.visit(base)">{{ t('common.back') }}</Button>
            </template>
        </PageHeader>

        <div class="max-w-2xl">
            <p v-if="log.description" class="text-ink-600 dark:text-ink-300">{{ log.description }}</p>

            <!-- Summary -->
            <dl class="mt-6 grid grid-cols-1 gap-px overflow-hidden rounded-card border border-ink-200 bg-ink-200 sm:grid-cols-2 dark:border-ink-800 dark:bg-ink-800">
                <div class="bg-white p-4 dark:bg-ink-900">
                    <dt class="text-sm text-ink-500">{{ t('workshop.vehicle') }}</dt>
                    <dd class="mt-1 font-medium">
                        <Link v-if="vehicle" :href="`/app/${page.props.tenant.slug}/fleet/${vehicle.id}`"
                            class="text-ink-900 hover:underline dark:text-ink-100">
                            {{ vehicle.make }} {{ vehicle.model }} · {{ vehicle.registration_number }}
                        </Link>
                        <span v-else class="text-ink-900 dark:text-ink-50">—</span>
                    </dd>
                </div>
                <div class="bg-white p-4 dark:bg-ink-900">
                    <dt class="text-sm text-ink-500">{{ t('workshop.mechanic') }}</dt>
                    <dd class="mt-1 font-medium text-ink-900 dark:text-ink-50">{{ log.mechanic?.name ?? '—' }}</dd>
                </div>
                <div class="bg-white p-4 dark:bg-ink-900">
                    <dt class="text-sm text-ink-500">{{ t('workshop.odometer') }}</dt>
                    <dd class="mt-1 font-medium text-ink-900 dark:text-ink-50">{{ log.odometer_reading ?? '—' }}</dd>
                </div>
                <div class="bg-white p-4 dark:bg-ink-900">
                    <dt class="text-sm text-ink-500">{{ t('workshop.started_at') }}</dt>
                    <dd class="mt-1 font-medium text-ink-900 dark:text-ink-50">{{ log.started_at ?? '—' }}</dd>
                </div>
                <div class="bg-white p-4 dark:bg-ink-900">
                    <dt class="text-sm text-ink-500">{{ t('workshop.completed_at') }}</dt>
                    <dd class="mt-1 font-medium text-ink-900 dark:text-ink-50">{{ log.completed_at ?? '—' }}</dd>
                </div>
            </dl>

            <!-- Parts -->
            <h2 class="mt-8 text-xs font-medium uppercase tracking-wide text-ink-500">{{ t('workshop.parts') }}</h2>
            <p v-if="!log.parts?.length" class="mt-2 text-sm text-ink-500">{{ t('workshop.no_parts') }}</p>
            <div v-else class="mt-2">
                <DataTable :columns="4" :empty="false">
                    <template #head>
                        <th class="px-4 py-2">{{ t('workshop.fields.part_name') }}</th>
                        <th class="px-4 py-2 text-right">{{ t('workshop.fields.quantity') }}</th>
                        <th class="px-4 py-2 text-right">{{ t('workshop.fields.unit_cost') }}</th>
                        <th class="px-4 py-2 text-right">{{ t('workshop.total') }}</th>
                    </template>
                    <tr v-for="part in log.parts" :key="part.id" class="text-ink-700 dark:text-ink-200">
                        <td class="px-4 py-2">{{ part.name }}</td>
                        <td class="px-4 py-2 text-right tabular-nums">{{ part.quantity }}</td>
                        <td class="px-4 py-2 text-right tabular-nums">{{ formatAUD(part.unit_cost) }}</td>
                        <td class="px-4 py-2 text-right tabular-nums">{{ formatAUD(part.total_cost) }}</td>
                    </tr>
                </DataTable>
            </div>

            <!-- Costs -->
            <dl class="mt-6 max-w-xs space-y-1 text-sm">
                <div class="flex justify-between">
                    <dt class="text-ink-500">{{ t('workshop.labour') }}</dt>
                    <dd class="font-medium tabular-nums text-ink-900 dark:text-ink-50">{{ formatAUD(log.labour_cost) }}</dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-ink-500">{{ t('workshop.parts_total') }}</dt>
                    <dd class="font-medium tabular-nums text-ink-900 dark:text-ink-50">{{ formatAUD(partsTotal) }}</dd>
                </div>
                <div class="flex justify-between border-t border-ink-200 pt-1 dark:border-ink-800">
                    <dt class="font-medium text-ink-900 dark:text-ink-50">{{ t('workshop.total') }}</dt>
                    <dd class="font-semibold tabular-nums text-ink-900 dark:text-ink-50">{{ formatAUD(log.total_cost) }}</dd>
                </div>
            </dl>
        </div>
    </AppLayout>
</template>
