<script setup>
// Invoice detail — design-system pass. Line items, payment history,
// record-payment form, outstanding balance, late-fee indicator, status badge.
import { computed } from 'vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import DataTable from '@/Components/UI/DataTable.vue';
import StatusBadge from '@/Components/UI/StatusBadge.vue';
import Button from '@/Components/UI/Button.vue';
import Input from '@/Components/UI/Input.vue';
import Select from '@/Components/UI/Select.vue';
import { useCurrency } from '@/composables/useCurrency';

const props = defineProps({
    invoice: { type: Object, required: true },
    customerBalance: { type: Number, default: 0 },
    methods: { type: Array, default: () => [] },
});

const { t } = useI18n();
const page = usePage();
const { formatAUD, toCents } = useCurrency();

const base = computed(() => `/app/${page.props.tenant.slug}/invoices`);

// invoice status enum → generic StatusBadge variant.
const statusVariants = {
    draft: 'neutral',
    sent: 'info',
    paid: 'success',
    overdue: 'danger',
    cancelled: 'neutral',
};

const outstanding = computed(() => (Number(props.invoice.total) || 0) - (Number(props.invoice.paid_amount) || 0));
const isPayable = computed(() => !['paid', 'cancelled'].includes(props.invoice.status));
const hasLateFee = computed(() =>
    (props.invoice.items ?? []).some((i) => String(i.description ?? '').startsWith('Late fee')),
);

function toDate(value) {
    return value ? String(value).slice(0, 10) : t('common.none');
}

// ── Record payment ─────────────────────────────────────────────────────────
// Amount entered in AUD, converted to integer cents on submit (transform).
const payForm = useForm({ amount: null, method: 'cash', notes: '', paid_at: '' });

function recordPayment() {
    payForm
        .transform((data) => ({
            ...data,
            amount: toCents(data.amount),
            paid_at: data.paid_at || undefined,
            notes: data.notes || undefined,
        }))
        .post(`${base.value}/${props.invoice.id}/payment`, {
            preserveScroll: true,
            onSuccess: () => payForm.reset(),
        });
}

// ── Manual overdue override ──────────────────────────────────────────────────
const overdueForm = useForm({});
function markOverdue() {
    overdueForm.post(`${base.value}/${props.invoice.id}/overdue`, { preserveScroll: true });
}
</script>

<template>
    <AppLayout>
        <Head :title="t('invoice.invoice_details')" />

        <PageHeader>
            <template #title>
                <span class="flex flex-wrap items-center gap-3">
                    {{ t('invoice.invoice_details') }} #{{ invoice.id }}
                    <StatusBadge :variant="statusVariants[invoice.status]" :label="t(`invoice.statuses.${invoice.status}`)" />
                    <StatusBadge v-if="hasLateFee" variant="warning" :label="t('invoice.late_fee_present')" />
                </span>
            </template>
            <template #actions>
                <Button variant="ghost" @click="router.visit(base)">{{ t('common.back') }}</Button>
            </template>
        </PageHeader>

        <div class="grid max-w-3xl grid-cols-1 gap-6">
            <!-- Summary -->
            <dl class="grid grid-cols-1 gap-px overflow-hidden rounded-card border border-ink-200 bg-ink-200 sm:grid-cols-2 dark:border-ink-800 dark:bg-ink-800">
                <div class="bg-white p-4 dark:bg-ink-900">
                    <dt class="text-sm text-ink-500">{{ t('invoice.fields.customer') }}</dt>
                    <dd class="mt-1 font-medium text-ink-900 dark:text-ink-50">{{ invoice.customer?.name ?? t('common.none') }}</dd>
                </div>
                <div class="bg-white p-4 dark:bg-ink-900">
                    <dt class="text-sm text-ink-500">{{ t('invoice.fields.type') }}</dt>
                    <dd class="mt-1 font-medium text-ink-900 dark:text-ink-50">{{ t(`invoice.types.${invoice.type}`) }}</dd>
                </div>
                <div class="bg-white p-4 dark:bg-ink-900">
                    <dt class="text-sm text-ink-500">{{ t('invoice.fields.billing_period') }}</dt>
                    <dd class="mt-1 font-medium text-ink-900 dark:text-ink-50">{{ toDate(invoice.billing_period_start) }} – {{ toDate(invoice.billing_period_end) }}</dd>
                </div>
                <div class="bg-white p-4 dark:bg-ink-900">
                    <dt class="text-sm text-ink-500">{{ t('invoice.fields.due_date') }}</dt>
                    <dd class="mt-1 font-medium text-ink-900 dark:text-ink-50">{{ toDate(invoice.due_date) }}</dd>
                </div>
            </dl>

            <!-- Line items -->
            <div>
                <h2 class="mb-3 text-lg font-semibold text-ink-900 dark:text-ink-50">{{ t('invoice.line_items') }}</h2>
                <DataTable :columns="4" :empty="!invoice.items || invoice.items.length === 0">
                    <template #head>
                        <th class="px-4 py-2">{{ t('invoice.fields.description') }}</th>
                        <th class="px-4 py-2">{{ t('invoice.fields.vehicle') }}</th>
                        <th class="px-4 py-2">{{ t('invoice.fields.period') }}</th>
                        <th class="px-4 py-2 text-right">{{ t('invoice.fields.amount') }}</th>
                    </template>
                    <tr v-for="item in invoice.items" :key="item.id" class="text-ink-700 dark:text-ink-200">
                        <td class="px-4 py-2">{{ item.description }}</td>
                        <td class="px-4 py-2">{{ item.vehicle?.registration_number ?? t('common.none') }}</td>
                        <td class="px-4 py-2">
                            <template v-if="item.period_start">{{ toDate(item.period_start) }} – {{ toDate(item.period_end) }}</template>
                            <template v-else>{{ t('common.none') }}</template>
                        </td>
                        <td class="px-4 py-2 text-right tabular-nums">{{ formatAUD(item.amount) }}</td>
                    </tr>
                </DataTable>
            </div>

            <!-- Totals -->
            <dl class="space-y-1 text-sm">
                <div class="flex justify-between"><dt class="text-ink-500">{{ t('invoice.subtotal') }}</dt><dd class="tabular-nums">{{ formatAUD(invoice.subtotal) }}</dd></div>
                <div class="flex justify-between font-medium text-ink-900 dark:text-ink-50"><dt>{{ t('invoice.total') }}</dt><dd class="tabular-nums">{{ formatAUD(invoice.total) }}</dd></div>
                <div class="flex justify-between"><dt class="text-ink-500">{{ t('invoice.paid') }}</dt><dd class="tabular-nums">{{ formatAUD(invoice.paid_amount) }}</dd></div>
                <div class="flex justify-between border-t border-ink-200 pt-1 text-base font-semibold text-ink-900 dark:border-ink-800 dark:text-ink-50"><dt>{{ t('invoice.balance_owing') }}</dt><dd class="tabular-nums">{{ formatAUD(outstanding) }}</dd></div>
                <div class="flex justify-between text-ink-500"><dt>{{ t('invoice.customer_balance') }}</dt><dd class="tabular-nums">{{ formatAUD(customerBalance) }}</dd></div>
            </dl>

            <!-- PDF + manual overdue -->
            <div class="flex flex-wrap items-center gap-4">
                <a v-if="invoice.pdf_path" :href="`${base}/${invoice.id}/pdf`" class="text-sm font-medium text-ink-900 hover:underline dark:text-ink-100">{{ t('invoice.download_pdf') }}</a>
                <span v-else class="text-sm text-ink-500">{{ t('invoice.pdf_pending') }}</span>
                <Button
                    v-if="isPayable && invoice.status !== 'overdue'"
                    variant="secondary"
                    size="sm"
                    @click="markOverdue"
                >
                    {{ t('invoice.mark_overdue') }}
                </Button>
            </div>

            <!-- Record payment -->
            <div v-if="isPayable" class="rounded-card border border-ink-200 bg-white p-4 shadow-subtle dark:border-ink-800 dark:bg-ink-900">
                <h2 class="text-lg font-semibold text-ink-900 dark:text-ink-50">{{ t('invoice.record_payment') }}</h2>
                <p class="mt-1 text-sm text-ink-500">{{ t('invoice.record_payment_hint') }}</p>
                <form class="mt-3 grid grid-cols-1 gap-4 sm:grid-cols-2" @submit.prevent="recordPayment">
                    <Input v-model="payForm.amount" type="number" step="0.01" min="0.01" :label="t('invoice.payment_amount_aud')" :error="payForm.errors.amount" />
                    <Select v-model="payForm.method" :label="t('invoice.payment_method')" :error="payForm.errors.method">
                        <option v-for="m in methods" :key="m" :value="m">{{ t(`invoice.methods.${m}`) }}</option>
                    </Select>
                    <Input v-model="payForm.paid_at" type="date" :label="t('invoice.payment_date')" />
                    <Input v-model="payForm.notes" :label="t('invoice.payment_notes')" />
                    <div class="sm:col-span-2">
                        <Button type="submit" :loading="payForm.processing">{{ t('invoice.submit_payment') }}</Button>
                    </div>
                </form>
            </div>

            <!-- Payment history -->
            <div>
                <h2 class="mb-3 text-lg font-semibold text-ink-900 dark:text-ink-50">{{ t('invoice.payment_history') }}</h2>
                <DataTable :columns="4" :empty="!invoice.payments || invoice.payments.length === 0">
                    <template #head>
                        <th class="px-4 py-2">{{ t('invoice.payment_date') }}</th>
                        <th class="px-4 py-2">{{ t('invoice.payment_method') }}</th>
                        <th class="px-4 py-2">{{ t('invoice.recorded_by') }}</th>
                        <th class="px-4 py-2 text-right">{{ t('invoice.fields.amount') }}</th>
                    </template>
                    <tr v-for="payment in invoice.payments" :key="payment.id" class="text-ink-700 dark:text-ink-200">
                        <td class="px-4 py-2">{{ toDate(payment.paid_at) }}</td>
                        <td class="px-4 py-2">{{ t(`invoice.methods.${payment.method}`) }}</td>
                        <td class="px-4 py-2">{{ payment.recorded_by?.name ?? t('invoice.system') }}</td>
                        <td class="px-4 py-2 text-right tabular-nums">{{ formatAUD(payment.amount) }}</td>
                    </tr>
                    <template #empty>
                        <div class="px-4 py-6 text-center text-sm text-ink-500">{{ t('invoice.no_payments') }}</div>
                    </template>
                </DataTable>
            </div>
        </div>
    </AppLayout>
</template>
