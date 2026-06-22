<script setup>
// Invoice detail. FUNCTIONAL ONLY — design pass later.
// Line items, payment history, record-payment form, outstanding balance,
// late-fee indicator, status badge.
import { computed } from 'vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import AppLayout from '@/Layouts/AppLayout.vue';
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
const flash = computed(() => page.props.flash?.success);
const flashError = computed(() => page.props.flash?.error);

const statusColors = {
    draft: 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300',
    sent: 'bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-300',
    paid: 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300',
    overdue: 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-300',
    cancelled: 'bg-slate-200 text-slate-600 dark:bg-slate-700 dark:text-slate-300',
};
const statusClass = computed(() => statusColors[props.invoice.status] ?? statusColors.draft);

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

        <div class="py-10">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <h1 class="text-2xl font-semibold">{{ t('invoice.invoice_details') }} #{{ invoice.id }}</h1>
                    <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium" :class="statusClass">
                        {{ t(`invoice.statuses.${invoice.status}`) }}
                    </span>
                    <span v-if="hasLateFee" class="inline-flex items-center rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-medium text-amber-800 dark:bg-amber-900/40 dark:text-amber-300">
                        {{ t('invoice.late_fee_present') }}
                    </span>
                </div>
                <Link :href="base" class="text-sm text-slate-500 hover:underline dark:text-slate-400">{{ t('common.back') }}</Link>
            </div>

            <p v-if="flash" class="mt-4 rounded border border-green-200 bg-green-50 px-3 py-2 text-sm text-green-800 dark:border-green-900 dark:bg-green-900/30 dark:text-green-300">{{ flash }}</p>
            <p v-if="flashError" class="mt-4 rounded border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-800 dark:border-red-900 dark:bg-red-900/30 dark:text-red-300">{{ flashError }}</p>

            <!-- Summary -->
            <dl class="mt-6 grid max-w-3xl grid-cols-1 gap-px overflow-hidden rounded border border-slate-200 bg-slate-200 sm:grid-cols-2 dark:border-slate-800 dark:bg-slate-800">
                <div class="bg-white p-4 dark:bg-slate-950">
                    <dt class="text-sm text-slate-500 dark:text-slate-400">{{ t('invoice.fields.customer') }}</dt>
                    <dd class="mt-1 font-medium">{{ invoice.customer?.name ?? t('common.none') }}</dd>
                </div>
                <div class="bg-white p-4 dark:bg-slate-950">
                    <dt class="text-sm text-slate-500 dark:text-slate-400">{{ t('invoice.fields.type') }}</dt>
                    <dd class="mt-1 font-medium">{{ t(`invoice.types.${invoice.type}`) }}</dd>
                </div>
                <div class="bg-white p-4 dark:bg-slate-950">
                    <dt class="text-sm text-slate-500 dark:text-slate-400">{{ t('invoice.fields.billing_period') }}</dt>
                    <dd class="mt-1 font-medium">{{ toDate(invoice.billing_period_start) }} – {{ toDate(invoice.billing_period_end) }}</dd>
                </div>
                <div class="bg-white p-4 dark:bg-slate-950">
                    <dt class="text-sm text-slate-500 dark:text-slate-400">{{ t('invoice.fields.due_date') }}</dt>
                    <dd class="mt-1 font-medium">{{ toDate(invoice.due_date) }}</dd>
                </div>
            </dl>

            <!-- Line items -->
            <div class="mt-6 max-w-3xl overflow-x-auto rounded border border-slate-200 dark:border-slate-800">
                <h2 class="px-4 pt-4 text-lg font-semibold">{{ t('invoice.line_items') }}</h2>
                <table class="mt-3 min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-800">
                    <thead class="text-left text-slate-500 dark:text-slate-400">
                        <tr>
                            <th class="px-4 py-2 font-medium">{{ t('invoice.fields.description') }}</th>
                            <th class="px-4 py-2 font-medium">{{ t('invoice.fields.vehicle') }}</th>
                            <th class="px-4 py-2 font-medium">{{ t('invoice.fields.period') }}</th>
                            <th class="px-4 py-2 text-right font-medium">{{ t('invoice.fields.amount') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        <tr v-for="item in invoice.items" :key="item.id">
                            <td class="px-4 py-2">{{ item.description }}</td>
                            <td class="px-4 py-2">{{ item.vehicle?.registration_number ?? t('common.none') }}</td>
                            <td class="px-4 py-2">
                                <template v-if="item.period_start">{{ toDate(item.period_start) }} – {{ toDate(item.period_end) }}</template>
                                <template v-else>{{ t('common.none') }}</template>
                            </td>
                            <td class="px-4 py-2 text-right">{{ formatAUD(item.amount) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Totals -->
            <dl class="mt-4 max-w-3xl space-y-1 text-sm">
                <div class="flex justify-between"><dt class="text-slate-500 dark:text-slate-400">{{ t('invoice.subtotal') }}</dt><dd>{{ formatAUD(invoice.subtotal) }}</dd></div>
                <div class="flex justify-between font-medium"><dt>{{ t('invoice.total') }}</dt><dd>{{ formatAUD(invoice.total) }}</dd></div>
                <div class="flex justify-between"><dt class="text-slate-500 dark:text-slate-400">{{ t('invoice.paid') }}</dt><dd>{{ formatAUD(invoice.paid_amount) }}</dd></div>
                <div class="flex justify-between border-t border-slate-200 pt-1 text-base font-semibold dark:border-slate-800"><dt>{{ t('invoice.balance_owing') }}</dt><dd>{{ formatAUD(outstanding) }}</dd></div>
                <div class="flex justify-between text-slate-500 dark:text-slate-400"><dt>{{ t('invoice.customer_balance') }}</dt><dd>{{ formatAUD(customerBalance) }}</dd></div>
            </dl>

            <!-- PDF + manual overdue -->
            <div class="mt-6 flex max-w-3xl flex-wrap items-center gap-4">
                <a v-if="invoice.pdf_path" :href="`${base}/${invoice.id}/pdf`" class="text-sm text-indigo-600 hover:underline dark:text-indigo-400">{{ t('invoice.download_pdf') }}</a>
                <span v-else class="text-sm text-slate-500 dark:text-slate-400">{{ t('invoice.pdf_pending') }}</span>
                <button
                    v-if="isPayable && invoice.status !== 'overdue'"
                    type="button"
                    class="rounded bg-slate-100 px-3 py-1.5 text-sm text-slate-700 hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700"
                    @click="markOverdue"
                >
                    {{ t('invoice.mark_overdue') }}
                </button>
            </div>

            <!-- Record payment -->
            <div v-if="isPayable" class="mt-6 max-w-3xl rounded border border-slate-200 p-4 dark:border-slate-800">
                <h2 class="text-lg font-semibold">{{ t('invoice.record_payment') }}</h2>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ t('invoice.record_payment_hint') }}</p>
                <form class="mt-3 grid grid-cols-1 gap-4 sm:grid-cols-2" @submit.prevent="recordPayment">
                    <div>
                        <label class="block text-sm text-slate-600 dark:text-slate-400">{{ t('invoice.payment_amount_aud') }}</label>
                        <input v-model="payForm.amount" type="number" step="0.01" min="0.01" class="mt-1 w-full rounded border border-slate-300 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900" />
                        <span v-if="payForm.errors.amount" class="mt-1 block text-xs text-red-600">{{ payForm.errors.amount }}</span>
                    </div>
                    <div>
                        <label class="block text-sm text-slate-600 dark:text-slate-400">{{ t('invoice.payment_method') }}</label>
                        <select v-model="payForm.method" class="mt-1 w-full rounded border border-slate-300 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900">
                            <option v-for="m in methods" :key="m" :value="m">{{ t(`invoice.methods.${m}`) }}</option>
                        </select>
                        <span v-if="payForm.errors.method" class="mt-1 block text-xs text-red-600">{{ payForm.errors.method }}</span>
                    </div>
                    <div>
                        <label class="block text-sm text-slate-600 dark:text-slate-400">{{ t('invoice.payment_date') }}</label>
                        <input v-model="payForm.paid_at" type="date" class="mt-1 w-full rounded border border-slate-300 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900" />
                    </div>
                    <div>
                        <label class="block text-sm text-slate-600 dark:text-slate-400">{{ t('invoice.payment_notes') }}</label>
                        <input v-model="payForm.notes" type="text" class="mt-1 w-full rounded border border-slate-300 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900" />
                    </div>
                    <div class="sm:col-span-2">
                        <button type="submit" :disabled="payForm.processing" class="rounded bg-slate-800 px-4 py-2 text-sm text-white disabled:opacity-50 dark:bg-slate-200 dark:text-slate-900">
                            {{ t('invoice.submit_payment') }}
                        </button>
                    </div>
                </form>
            </div>

            <!-- Payment history -->
            <div class="mt-6 max-w-3xl">
                <h2 class="text-lg font-semibold">{{ t('invoice.payment_history') }}</h2>
                <div class="mt-3 overflow-x-auto rounded border border-slate-200 dark:border-slate-800">
                    <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-800">
                        <thead class="text-left text-slate-500 dark:text-slate-400">
                            <tr>
                                <th class="px-4 py-2 font-medium">{{ t('invoice.payment_date') }}</th>
                                <th class="px-4 py-2 font-medium">{{ t('invoice.payment_method') }}</th>
                                <th class="px-4 py-2 font-medium">{{ t('invoice.recorded_by') }}</th>
                                <th class="px-4 py-2 text-right font-medium">{{ t('invoice.fields.amount') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            <tr v-if="!invoice.payments || invoice.payments.length === 0">
                                <td colspan="4" class="px-4 py-6 text-center text-slate-500 dark:text-slate-400">{{ t('invoice.no_payments') }}</td>
                            </tr>
                            <tr v-for="payment in invoice.payments" :key="payment.id">
                                <td class="px-4 py-2">{{ toDate(payment.paid_at) }}</td>
                                <td class="px-4 py-2">{{ t(`invoice.methods.${payment.method}`) }}</td>
                                <td class="px-4 py-2">{{ payment.recorded_by?.name ?? t('invoice.system') }}</td>
                                <td class="px-4 py-2 text-right">{{ formatAUD(payment.amount) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
