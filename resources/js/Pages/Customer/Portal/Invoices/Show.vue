<script setup>
// Customer-portal invoice detail + pay form. CustomerLayout. FUNCTIONAL ONLY.
import { computed } from 'vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import CustomerLayout from '@/Layouts/CustomerLayout.vue';
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

        <div class="mb-6 flex items-center justify-between">
            <h1 class="text-2xl font-semibold">{{ t('customer.portal.invoice_number', { id: invoice.id }) }}</h1>
            <a
                v-if="invoice.has_pdf"
                :href="`${base}/invoices/${invoice.id}/pdf`"
                class="text-sm text-indigo-600 hover:underline dark:text-indigo-400"
            >
                {{ t('customer.portal.download_pdf') }}
            </a>
        </div>

        <!-- Totals -->
        <div class="grid gap-4 sm:grid-cols-4">
            <div class="rounded-lg border border-slate-200 p-4 dark:border-slate-800">
                <p class="text-xs text-slate-500 dark:text-slate-400">{{ t('customer.portal.subtotal') }}</p>
                <p class="mt-1 font-semibold">{{ formatAUD(invoice.subtotal) }}</p>
            </div>
            <div class="rounded-lg border border-slate-200 p-4 dark:border-slate-800">
                <p class="text-xs text-slate-500 dark:text-slate-400">{{ t('customer.portal.total') }}</p>
                <p class="mt-1 font-semibold">{{ formatAUD(invoice.total) }}</p>
            </div>
            <div class="rounded-lg border border-slate-200 p-4 dark:border-slate-800">
                <p class="text-xs text-slate-500 dark:text-slate-400">{{ t('customer.portal.paid') }}</p>
                <p class="mt-1 font-semibold">{{ formatAUD(invoice.paid_amount) }}</p>
            </div>
            <div class="rounded-lg border border-slate-200 p-4 dark:border-slate-800">
                <p class="text-xs text-slate-500 dark:text-slate-400">{{ t('customer.portal.outstanding') }}</p>
                <p class="mt-1 font-semibold" :class="invoice.outstanding > 0 ? 'text-red-600 dark:text-red-400' : ''">
                    {{ formatAUD(invoice.outstanding) }}
                </p>
            </div>
        </div>

        <!-- Line items -->
        <h2 class="mb-3 mt-8 text-lg font-semibold">{{ t('customer.portal.line_items') }}</h2>
        <div class="overflow-hidden rounded-lg border border-slate-200 dark:border-slate-800">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-left text-slate-500 dark:bg-slate-900 dark:text-slate-400">
                    <tr>
                        <th class="px-4 py-2 font-medium">{{ t('customer.portal.description') }}</th>
                        <th class="px-4 py-2 font-medium">{{ t('customer.portal.period') }}</th>
                        <th class="px-4 py-2 text-right font-medium">{{ t('customer.portal.amount') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 dark:divide-slate-800">
                    <tr v-for="item in invoice.items" :key="item.id">
                        <td class="px-4 py-3">
                            {{ item.description }}
                            <span v-if="item.vehicle" class="block text-xs text-slate-500 dark:text-slate-400">
                                {{ item.vehicle.make }} {{ item.vehicle.model }} · {{ item.vehicle.registration_number }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-slate-500 dark:text-slate-400">
                            <span v-if="item.period_start">{{ item.period_start }} → {{ item.period_end ?? '—' }}</span>
                            <span v-else>—</span>
                        </td>
                        <td class="px-4 py-3 text-right">{{ formatAUD(item.amount) }}</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Payment history -->
        <h2 class="mb-3 mt-8 text-lg font-semibold">{{ t('customer.portal.payment_history') }}</h2>
        <p v-if="invoice.payments.length === 0" class="text-sm text-slate-500 dark:text-slate-400">
            {{ t('customer.portal.no_payments') }}
        </p>
        <div v-else class="overflow-hidden rounded-lg border border-slate-200 dark:border-slate-800">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-left text-slate-500 dark:bg-slate-900 dark:text-slate-400">
                    <tr>
                        <th class="px-4 py-2 font-medium">{{ t('customer.portal.date') }}</th>
                        <th class="px-4 py-2 font-medium">{{ t('customer.portal.method') }}</th>
                        <th class="px-4 py-2 text-right font-medium">{{ t('customer.portal.amount') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 dark:divide-slate-800">
                    <tr v-for="payment in invoice.payments" :key="payment.id">
                        <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ payment.paid_at }}</td>
                        <td class="px-4 py-3">{{ payment.method }}</td>
                        <td class="px-4 py-3 text-right">{{ formatAUD(payment.amount) }}</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Pay now -->
        <div v-if="payable" class="mt-8 rounded-lg border border-slate-200 p-5 dark:border-slate-800">
            <h2 class="mb-4 text-lg font-semibold">{{ t('customer.portal.pay_now') }}</h2>
            <form class="grid gap-4 sm:grid-cols-3" @submit.prevent="submit">
                <div>
                    <label for="amount" class="block text-sm font-medium">{{ t('customer.portal.pay_amount') }}</label>
                    <input
                        id="amount"
                        v-model="form.amount"
                        type="number"
                        min="0.01"
                        step="0.01"
                        required
                        class="mt-1 w-full rounded border border-slate-300 px-3 py-2 dark:border-slate-700 dark:bg-slate-900"
                    />
                    <p v-if="form.errors.amount" class="mt-1 text-sm text-red-600">{{ form.errors.amount }}</p>
                </div>
                <div>
                    <label for="method" class="block text-sm font-medium">{{ t('customer.portal.pay_method') }}</label>
                    <select
                        id="method"
                        v-model="form.method"
                        class="mt-1 w-full rounded border border-slate-300 px-3 py-2 dark:border-slate-700 dark:bg-slate-900"
                    >
                        <option value="cash">{{ t('customer.portal.method_cash') }}</option>
                        <option value="bank_transfer">{{ t('customer.portal.method_bank_transfer') }}</option>
                    </select>
                    <p v-if="form.errors.method" class="mt-1 text-sm text-red-600">{{ form.errors.method }}</p>
                </div>
                <div>
                    <label for="notes" class="block text-sm font-medium">{{ t('customer.portal.pay_notes') }}</label>
                    <input
                        id="notes"
                        v-model="form.notes"
                        type="text"
                        class="mt-1 w-full rounded border border-slate-300 px-3 py-2 dark:border-slate-700 dark:bg-slate-900"
                    />
                </div>
                <div class="sm:col-span-3">
                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="rounded bg-indigo-600 px-4 py-2 text-white disabled:opacity-50"
                    >
                        {{ t('customer.portal.pay_submit') }}
                    </button>
                </div>
            </form>
        </div>
    </CustomerLayout>
</template>
