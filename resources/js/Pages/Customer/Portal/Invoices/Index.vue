<script setup>
// Customer-portal invoice list. CustomerLayout. FUNCTIONAL ONLY.
import { computed } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import CustomerLayout from '@/Layouts/CustomerLayout.vue';
import { useCurrency } from '@/composables/useCurrency';

const { t } = useI18n();
const { formatAUD } = useCurrency();
const page = usePage();

defineProps({
    invoices: { type: Object, required: true },
});

const base = computed(() => `/portal/${page.props.tenant.slug}`);

function statusClass(status) {
    return {
        paid: 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300',
        overdue: 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-300',
        cancelled: 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300',
    }[status] ?? 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300';
}
</script>

<template>
    <CustomerLayout>
        <Head :title="t('customer.portal.invoices')" />

        <h1 class="mb-6 text-2xl font-semibold">{{ t('customer.portal.invoices') }}</h1>

        <p v-if="invoices.data.length === 0" class="text-sm text-slate-500 dark:text-slate-400">
            {{ t('customer.portal.no_invoices') }}
        </p>

        <div v-else class="overflow-hidden rounded-lg border border-slate-200 dark:border-slate-800">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-left text-slate-500 dark:bg-slate-900 dark:text-slate-400">
                    <tr>
                        <th class="px-4 py-2 font-medium">{{ t('customer.portal.invoice') }}</th>
                        <th class="px-4 py-2 font-medium">{{ t('customer.portal.due') }}</th>
                        <th class="px-4 py-2 font-medium">{{ t('customer.portal.status') }}</th>
                        <th class="px-4 py-2 text-right font-medium">{{ t('customer.portal.total') }}</th>
                        <th class="px-4 py-2 text-right font-medium">{{ t('customer.portal.outstanding') }}</th>
                        <th class="px-4 py-2"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 dark:divide-slate-800">
                    <tr v-for="invoice in invoices.data" :key="invoice.id">
                        <td class="px-4 py-3 font-medium">{{ t('customer.portal.invoice_number', { id: invoice.id }) }}</td>
                        <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ invoice.due_date ?? '—' }}</td>
                        <td class="px-4 py-3">
                            <span class="rounded-full px-2 py-0.5 text-xs font-medium uppercase" :class="statusClass(invoice.status)">
                                {{ invoice.status }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-right">{{ formatAUD(invoice.total) }}</td>
                        <td class="px-4 py-3 text-right">{{ formatAUD(invoice.outstanding) }}</td>
                        <td class="px-4 py-3 text-right">
                            <Link
                                :href="`${base}/invoices/${invoice.id}`"
                                class="font-medium text-indigo-600 hover:underline dark:text-indigo-400"
                            >
                                {{ invoice.outstanding > 0 && invoice.status !== 'cancelled'
                                    ? t('customer.portal.pay_now')
                                    : t('customer.portal.view') }}
                            </Link>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </CustomerLayout>
</template>
