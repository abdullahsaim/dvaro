<script setup>
// Customer-portal dashboard. CustomerLayout. Design-system pass.
import { computed } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import CustomerLayout from '@/Layouts/CustomerLayout.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import StatCard from '@/Components/UI/StatCard.vue';
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

        <PageHeader :title="t('customer.portal.welcome')" />

        <div class="grid gap-4 sm:grid-cols-2">
            <!-- Outstanding balance -->
            <StatCard :label="t('customer.portal.outstanding_balance')">
                <span :class="outstandingBalance > 0 ? 'text-danger-600 dark:text-danger-500' : 'text-success-600 dark:text-success-500'">
                    {{ formatAUD(outstandingBalance) }}
                </span>
                <template v-if="outstandingBalance <= 0" #description>{{ t('customer.portal.balance_clear') }}</template>
            </StatCard>

            <!-- Current rental -->
            <div class="rounded-card border border-ink-200 bg-white p-5 shadow-subtle dark:border-ink-800 dark:bg-ink-900">
                <p class="text-sm font-medium text-ink-500">{{ t('customer.portal.current_rental') }}</p>
                <template v-if="currentRental">
                    <p class="mt-1 text-lg font-medium text-ink-900 dark:text-ink-50">{{ vehicleLabel(currentRental.vehicle) }}</p>
                    <p class="mt-1 text-sm text-ink-500">{{ currentRental.start_date }} → {{ currentRental.end_date ?? '—' }}</p>
                    <Link :href="`${base}/agreements/${currentRental.id}`" class="mt-2 inline-block text-sm font-medium text-ink-900 hover:underline dark:text-ink-100">
                        {{ t('customer.portal.agreement') }} →
                    </Link>
                </template>
                <p v-else class="mt-1 text-sm text-ink-500">{{ t('customer.portal.no_current_rental') }}</p>
            </div>
        </div>

        <!-- Recent invoices -->
        <div class="mt-8">
            <div class="mb-3 flex items-center justify-between">
                <h2 class="text-lg font-semibold text-ink-900 dark:text-ink-50">{{ t('customer.portal.recent_invoices') }}</h2>
                <Link :href="`${base}/invoices`" class="text-sm font-medium text-ink-900 hover:underline dark:text-ink-100">
                    {{ t('customer.portal.view_all_invoices') }} →
                </Link>
            </div>

            <p v-if="recentInvoices.length === 0" class="text-sm text-ink-500">{{ t('customer.portal.no_invoices') }}</p>

            <div v-else class="divide-y divide-ink-100 overflow-hidden rounded-card border border-ink-200 dark:divide-ink-800 dark:border-ink-800">
                <Link
                    v-for="invoice in recentInvoices"
                    :key="invoice.id"
                    :href="`${base}/invoices/${invoice.id}`"
                    class="flex items-center justify-between bg-white px-4 py-3 hover:bg-ink-50 dark:bg-ink-900 dark:hover:bg-ink-800/50"
                >
                    <div>
                        <p class="font-medium text-ink-900 dark:text-ink-50">{{ t('customer.portal.invoice_number', { id: invoice.id }) }}</p>
                        <p class="text-sm text-ink-500">{{ t('customer.portal.due') }}: {{ invoice.due_date ?? '—' }}</p>
                    </div>
                    <div class="text-right">
                        <p class="font-medium tabular-nums text-ink-900 dark:text-ink-50">{{ formatAUD(invoice.total) }}</p>
                        <p class="text-xs uppercase text-ink-500">{{ invoice.status }}</p>
                    </div>
                </Link>
            </div>
        </div>
    </CustomerLayout>
</template>
