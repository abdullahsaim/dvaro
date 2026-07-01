<script setup>
// Customer-portal agreement list. CustomerLayout. Design-system pass.
import { computed } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import CustomerLayout from '@/Layouts/CustomerLayout.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import DataTable from '@/Components/UI/DataTable.vue';
import StatusBadge from '@/Components/UI/StatusBadge.vue';

const { t } = useI18n();
const page = usePage();

defineProps({
    agreements: { type: Object, required: true },
});

const base = computed(() => `/portal/${page.props.tenant.slug}`);

const statusVariants = {
    draft: 'neutral',
    signed: 'info',
    active: 'success',
    completed: 'neutral',
    cancelled: 'danger',
};
</script>

<template>
    <CustomerLayout>
        <Head :title="t('customer.portal.agreements')" />

        <PageHeader :title="t('customer.portal.agreements')" />

        <DataTable :columns="6" :empty="agreements.data.length === 0" :pagination="agreements">
            <template #head>
                <th class="px-4 py-2">{{ t('customer.portal.agreement') }}</th>
                <th class="px-4 py-2">{{ t('customer.portal.type') }}</th>
                <th class="px-4 py-2">{{ t('customer.portal.vehicle') }}</th>
                <th class="px-4 py-2">{{ t('customer.portal.status') }}</th>
                <th class="px-4 py-2">{{ t('customer.portal.start_date') }}</th>
                <th class="px-4 py-2 text-right">{{ t('common.actions') }}</th>
            </template>
            <tr v-for="agreement in agreements.data" :key="agreement.id" class="text-ink-700 dark:text-ink-200">
                <td class="px-4 py-3 font-medium text-ink-900 dark:text-ink-50">
                    {{ t('customer.portal.agreement_number', { id: agreement.id }) }}
                    <span class="text-xs text-ink-500">v{{ agreement.version }}</span>
                </td>
                <td class="px-4 py-3 capitalize">{{ agreement.type }}</td>
                <td class="px-4 py-3 text-ink-500">
                    <span v-if="agreement.vehicle">{{ agreement.vehicle.make }} {{ agreement.vehicle.model }}</span>
                    <span v-else>—</span>
                </td>
                <td class="px-4 py-3">
                    <StatusBadge :variant="statusVariants[agreement.status]" :label="agreement.status" />
                </td>
                <td class="px-4 py-3 text-ink-500">{{ agreement.start_date ?? '—' }}</td>
                <td class="px-4 py-3 text-right">
                    <Link :href="`${base}/agreements/${agreement.id}`" class="text-sm font-medium text-ink-900 hover:underline dark:text-ink-100">
                        {{ t('customer.portal.view') }}
                    </Link>
                </td>
            </tr>
            <template #empty>
                <div class="px-4 py-6 text-center text-sm text-ink-400">{{ t('customer.portal.no_agreements') }}</div>
            </template>
        </DataTable>
    </CustomerLayout>
</template>
