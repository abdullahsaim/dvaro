<script setup>
// Customer-portal invoice list. CustomerLayout. Design-system pass.
import { computed } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import CustomerLayout from '@/Layouts/CustomerLayout.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import DataTable from '@/Components/UI/DataTable.vue';
import StatusBadge from '@/Components/UI/StatusBadge.vue';
import { useCurrency } from '@/composables/useCurrency';

const { t } = useI18n();
const { formatAUD } = useCurrency();
const page = usePage();

defineProps({
    invoices: { type: Object, required: true },
});

const base = computed(() => `/portal/${page.props.tenant.slug}`);

function statusVariant(status) {
    return { paid: 'success', overdue: 'danger', cancelled: 'neutral' }[status] ?? 'warning';
}
</script>

<template>
    <CustomerLayout>
        <Head :title="t('customer.portal.invoices')" />

        <PageHeader :title="t('customer.portal.invoices')" />

        <DataTable :columns="6" :empty="invoices.data.length === 0" :pagination="invoices">
            <template #head>
                <th class="px-4 py-2">{{ t('customer.portal.invoice') }}</th>
                <th class="px-4 py-2">{{ t('customer.portal.due') }}</th>
                <th class="px-4 py-2">{{ t('customer.portal.status') }}</th>
                <th class="px-4 py-2 text-right">{{ t('customer.portal.total') }}</th>
                <th class="px-4 py-2 text-right">{{ t('customer.portal.outstanding') }}</th>
                <th class="px-4 py-2 text-right">{{ t('common.actions') }}</th>
            </template>
            <tr v-for="invoice in invoices.data" :key="invoice.id" class="text-ink-700 dark:text-ink-200">
                <td class="px-4 py-3 font-medium text-ink-900 dark:text-ink-50">{{ t('customer.portal.invoice_number', { id: invoice.id }) }}</td>
                <td class="px-4 py-3 text-ink-500">{{ invoice.due_date ?? '—' }}</td>
                <td class="px-4 py-3">
                    <StatusBadge :variant="statusVariant(invoice.status)" :label="invoice.status" />
                </td>
                <td class="px-4 py-3 text-right tabular-nums">{{ formatAUD(invoice.total) }}</td>
                <td class="px-4 py-3 text-right tabular-nums">{{ formatAUD(invoice.outstanding) }}</td>
                <td class="px-4 py-3 text-right">
                    <Link :href="`${base}/invoices/${invoice.id}`" class="text-sm font-medium text-ink-900 hover:underline dark:text-ink-100">
                        {{ invoice.outstanding > 0 && invoice.status !== 'cancelled'
                            ? t('customer.portal.pay_now')
                            : t('customer.portal.view') }}
                    </Link>
                </td>
            </tr>
            <template #empty>
                <div class="px-4 py-6 text-center text-sm text-ink-400">{{ t('customer.portal.no_invoices') }}</div>
            </template>
        </DataTable>
    </CustomerLayout>
</template>
