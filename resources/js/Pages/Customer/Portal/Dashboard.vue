<script setup>
// Customer-portal dashboard. CustomerLayout. FUNCTIONAL ONLY — design pass later.
import { computed } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import CustomerLayout from '@/Layouts/CustomerLayout.vue';
import { useCurrency } from '@/composables/useCurrency';

const { t } = useI18n();
const { formatAUD } = useCurrency();
const page = usePage();

defineProps({
    outstandingBalance: { type: Number, required: true },
    currentRental: { type: Object, default: null },
    recentInvoices: { type: Array, default: () => [] },
});

const base = computed(() => `/portal/${page.props.tenant.slug}`);

function vehicleLabel(v) {
    if (!v) return '—';
    return `${v.make} ${v.model} · ${v.registration_number}`;
}
</script>

<template>
    <CustomerLayout>
        <Head :title="t('customer.portal.dashboard')" />

        <h1 class="mb-6 text-2xl font-semibold">{{ t('customer.portal.welcome') }}</h1>

        <div class="grid gap-4 sm:grid-cols-2">
            <!-- Outstanding balance -->
            <div class="rounded-lg border border-slate-200 p-5 dark:border-slate-800">
                <p class="text-sm text-slate-500 dark:text-slate-400">{{ t('customer.portal.outstanding_balance') }}</p>
                <p
                    class="mt-1 text-2xl font-semibold"
                    :class="outstandingBalance > 0 ? 'text-red-600 dark:text-red-400' : 'text-green-600 dark:text-green-400'"
                >
                    {{ formatAUD(outstandingBalance) }}
                </p>
                <p v-if="outstandingBalance <= 0" class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                    {{ t('customer.portal.balance_clear') }}
                </p>
            </div>

            <!-- Current rental -->
            <div class="rounded-lg border border-slate-200 p-5 dark:border-slate-800">
                <p class="text-sm text-slate-500 dark:text-slate-400">{{ t('customer.portal.current_rental') }}</p>
                <template v-if="currentRental">
                    <p class="mt-1 text-lg font-medium">{{ vehicleLabel(currentRental.vehicle) }}</p>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                        {{ currentRental.start_date }} → {{ currentRental.end_date ?? '—' }}
                    </p>
                    <Link
                        :href="`${base}/agreements/${currentRental.id}`"
                        class="mt-2 inline-block text-sm text-indigo-600 hover:underline dark:text-indigo-400"
                    >
                        {{ t('customer.portal.agreement') }} →
                    </Link>
                </template>
                <p v-else class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                    {{ t('customer.portal.no_current_rental') }}
                </p>
            </div>
        </div>

        <!-- Recent invoices -->
        <div class="mt-8">
            <div class="mb-3 flex items-center justify-between">
                <h2 class="text-lg font-semibold">{{ t('customer.portal.recent_invoices') }}</h2>
                <Link :href="`${base}/invoices`" class="text-sm text-indigo-600 hover:underline dark:text-indigo-400">
                    {{ t('customer.portal.view_all_invoices') }} →
                </Link>
            </div>

            <p v-if="recentInvoices.length === 0" class="text-sm text-slate-500 dark:text-slate-400">
                {{ t('customer.portal.no_invoices') }}
            </p>

            <div v-else class="divide-y divide-slate-200 rounded-lg border border-slate-200 dark:divide-slate-800 dark:border-slate-800">
                <Link
                    v-for="invoice in recentInvoices"
                    :key="invoice.id"
                    :href="`${base}/invoices/${invoice.id}`"
                    class="flex items-center justify-between px-4 py-3 hover:bg-slate-50 dark:hover:bg-slate-900"
                >
                    <div>
                        <p class="font-medium">{{ t('customer.portal.invoice_number', { id: invoice.id }) }}</p>
                        <p class="text-sm text-slate-500 dark:text-slate-400">{{ t('customer.portal.due') }}: {{ invoice.due_date ?? '—' }}</p>
                    </div>
                    <div class="text-right">
                        <p class="font-medium">{{ formatAUD(invoice.total) }}</p>
                        <p class="text-xs uppercase text-slate-500 dark:text-slate-400">{{ invoice.status }}</p>
                    </div>
                </Link>
            </div>
        </div>
    </CustomerLayout>
</template>
