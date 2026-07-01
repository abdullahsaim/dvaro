<script setup>
// Platform-wide subscriptions — status + plan filters. Read-only. Design-system pass.
import { Head, Link, router } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import SuperAdminLayout from '@/Layouts/SuperAdminLayout.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import DataTable from '@/Components/UI/DataTable.vue';
import StatusBadge from '@/Components/UI/StatusBadge.vue';
import Select from '@/Components/UI/Select.vue';

const props = defineProps({
    subscriptions: { type: Object, required: true },
    statuses: { type: Array, required: true },
    activeStatus: { type: String, default: null },
    plans: { type: Array, required: true },
    activePlanId: { type: Number, default: null },
});

const { t } = useI18n();

const statusVariants = {
    active: 'success',
    trialing: 'info',
    trial: 'info',
    suspended: 'danger',
    cancelled: 'neutral',
    past_due: 'warning',
};

function filter(params) {
    router.get('/superadmin/subscriptions', {
        status: 'status' in params ? params.status : props.activeStatus,
        plan_id: 'plan_id' in params ? params.plan_id : props.activePlanId,
    }, { preserveState: true, replace: true });
}

function onPlanChange(e) {
    const v = e.target.value;
    filter({ plan_id: v === '' ? null : Number(v) });
}

function formatDate(value) {
    return value ? new Date(value).toLocaleDateString('en-AU') : '—';
}
</script>

<template>
    <SuperAdminLayout>
        <Head :title="t('superadmin.subscriptions.title')" />

        <PageHeader :title="t('superadmin.subscriptions.title')" />

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
                    {{ t('common.all') }}
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
                    {{ t(`superadmin.statuses.${s}`) }}
                </button>
            </div>

            <Select :model-value="activePlanId ?? ''" class="ml-auto w-56" @change="onPlanChange">
                <option value="">{{ t('superadmin.subscriptions.all_plans') }}</option>
                <option v-for="p in plans" :key="p.id" :value="p.id">{{ p.name }}</option>
            </Select>
        </div>

        <DataTable :columns="6" :empty="!subscriptions.data.length" :pagination="subscriptions">
            <template #head>
                <th class="px-4 py-2">{{ t('superadmin.subscriptions.tenant') }}</th>
                <th class="px-4 py-2">{{ t('superadmin.subscriptions.plan') }}</th>
                <th class="px-4 py-2">{{ t('fleet.fields.status') }}</th>
                <th class="px-4 py-2">{{ t('superadmin.subscriptions.billing_cycle') }}</th>
                <th class="px-4 py-2">{{ t('superadmin.subscriptions.period_end') }}</th>
                <th class="px-4 py-2 text-right">{{ t('common.actions') }}</th>
            </template>
            <tr v-for="sub in subscriptions.data" :key="sub.id" class="text-ink-700 dark:text-ink-200">
                <td class="px-4 py-2 font-medium text-ink-900 dark:text-ink-50">{{ sub.tenant_name ?? '—' }}</td>
                <td class="px-4 py-2 text-ink-500">{{ sub.plan_name ?? '—' }}</td>
                <td class="px-4 py-2">
                    <StatusBadge :variant="statusVariants[sub.status]" :label="t(`superadmin.statuses.${sub.status}`)" />
                </td>
                <td class="px-4 py-2 text-ink-500">{{ sub.billing_cycle }}</td>
                <td class="px-4 py-2 text-ink-500">{{ formatDate(sub.current_period_end) }}</td>
                <td class="px-4 py-2 text-right">
                    <Link :href="`/superadmin/subscriptions/${sub.id}`" class="text-sm font-medium text-ink-900 hover:underline dark:text-ink-100">
                        {{ t('superadmin.subscriptions.view') }}
                    </Link>
                </td>
            </tr>
            <template #empty>
                <div class="px-4 py-6 text-center text-sm text-ink-400">{{ t('superadmin.subscriptions.empty') }}</div>
            </template>
        </DataTable>
    </SuperAdminLayout>
</template>
