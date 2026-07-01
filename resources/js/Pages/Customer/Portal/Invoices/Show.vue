<script setup>
// Customer-portal invoice detail + pay form. CustomerLayout. Design-system pass.
import { computed } from 'vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import CustomerLayout from '@/Layouts/CustomerLayout.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import StatCard from '@/Components/UI/StatCard.vue';
import DataTable from '@/Components/UI/DataTable.vue';
import Button from '@/Components/UI/Button.vue';
import Input from '@/Components/UI/Input.vue';
import Select from '@/Components/UI/Select.vue';
import { useCurrency } from '@/composables/useCurrency';

const { t } = useI18n();
const { formatAUD, toCents } = useCurrency();
const page = usePage();

const props = defineProps({
    invoice: { type: Object, required: true },
});

const base = computed(() => `/portal/${page.props.tenant.slug}`);
const payable = computed(() => props.invoice.outstanding > 0 && props.invoice.status !== 'cancelled');

const form = useForm({
    amount: '',
    method: 'cash',
    notes: '',
});

function submit() {
    form
        .transform((data) => ({ ...data, amount: toCents(data.amount) }))
        .post(`${base.value}/invoices/${props.invoice.id}/pay`, {
            onSuccess: () => form.reset(),
        });
}
</script>

<template>
    <CustomerLayout>
        <Head :title="t('customer.portal.invoice_number', { id: invoice.id })" />

        <PageHeader :title="t('customer.portal.invoice_number', { id: invoice.id })">
            <template #actions>
                <a
                    v-if="invoice.has_pdf"
                    :href="`${base}/invoices/${invoice.id}/pdf`"
                    class="text-sm font-medium text-ink-900 hover:underline dark:text-ink-100"
                >
                    {{ t('customer.portal.download_pdf') }}
                </a>
            </template>
        </PageHeader>

        <!-- Totals -->
        <div class="grid gap-4 sm:grid-cols-4">
            <StatCard :label="t('customer.portal.subtotal')" :value="formatAUD(invoice.subtotal)" />
            <StatCard :label="t('customer.portal.total')" :value="formatAUD(invoice.total)" />
            <StatCard :label="t('customer.portal.paid')" :value="formatAUD(invoice.paid_amount)" />
            <StatCard :label="t('customer.portal.outstanding')">
                <span :class="invoice.outstanding > 0 ? 'text-danger-600 dark:text-danger-500' : ''">{{ formatAUD(invoice.outstanding) }}</span>
            </StatCard>
        </div>

        <!-- Line items -->
        <h2 class="mb-3 mt-8 text-lg font-semibold text-ink-900 dark:text-ink-50">{{ t('customer.portal.line_items') }}</h2>
        <DataTable :columns="3" :empty="!invoice.items || invoice.items.length === 0">
            <template #head>
                <th class="px-4 py-2">{{ t('customer.portal.description') }}</th>
                <th class="px-4 py-2">{{ t('customer.portal.period') }}</th>
                <th class="px-4 py-2 text-right">{{ t('customer.portal.amount') }}</th>
            </template>
            <tr v-for="item in invoice.items" :key="item.id" class="text-ink-700 dark:text-ink-200">
                <td class="px-4 py-3">
                    {{ item.description }}
                    <span v-if="item.vehicle" class="block text-xs text-ink-500">
                        {{ item.vehicle.make }} {{ item.vehicle.model }} · {{ item.vehicle.registration_number }}
                    </span>
                </td>
                <td class="px-4 py-3 text-ink-500">
                    <span v-if="item.period_start">{{ item.period_start }} → {{ item.period_end ?? '—' }}</span>
                    <span v-else>—</span>
                </td>
                <td class="px-4 py-3 text-right tabular-nums">{{ formatAUD(item.amount) }}</td>
            </tr>
        </DataTable>

        <!-- Payment history -->
        <h2 class="mb-3 mt-8 text-lg font-semibold text-ink-900 dark:text-ink-50">{{ t('customer.portal.payment_history') }}</h2>
        <DataTable :columns="3" :empty="invoice.payments.length === 0">
            <template #head>
                <th class="px-4 py-2">{{ t('customer.portal.date') }}</th>
                <th class="px-4 py-2">{{ t('customer.portal.method') }}</th>
                <th class="px-4 py-2 text-right">{{ t('customer.portal.amount') }}</th>
            </template>
            <tr v-for="payment in invoice.payments" :key="payment.id" class="text-ink-700 dark:text-ink-200">
                <td class="px-4 py-3 text-ink-500">{{ payment.paid_at }}</td>
                <td class="px-4 py-3">{{ payment.method }}</td>
                <td class="px-4 py-3 text-right tabular-nums">{{ formatAUD(payment.amount) }}</td>
            </tr>
            <template #empty>
                <div class="px-4 py-6 text-center text-sm text-ink-400">{{ t('customer.portal.no_payments') }}</div>
            </template>
        </DataTable>

        <!-- Pay now -->
        <div v-if="payable" class="mt-8 rounded-card border border-ink-200 bg-white p-5 shadow-subtle dark:border-ink-800 dark:bg-ink-900">
            <h2 class="mb-4 text-lg font-semibold text-ink-900 dark:text-ink-50">{{ t('customer.portal.pay_now') }}</h2>
            <form class="grid gap-4 sm:grid-cols-3" @submit.prevent="submit">
                <Input v-model="form.amount" type="number" min="0.01" step="0.01" required :label="t('customer.portal.pay_amount')" :error="form.errors.amount" />
                <Select v-model="form.method" :label="t('customer.portal.pay_method')" :error="form.errors.method">
                    <option value="cash">{{ t('customer.portal.method_cash') }}</option>
                    <option value="bank_transfer">{{ t('customer.portal.method_bank_transfer') }}</option>
                </Select>
                <Input v-model="form.notes" :label="t('customer.portal.pay_notes')" />
                <div class="sm:col-span-3">
                    <Button type="submit" :loading="form.processing">{{ t('customer.portal.pay_submit') }}</Button>
                </div>
            </form>
        </div>
    </CustomerLayout>
</template>
