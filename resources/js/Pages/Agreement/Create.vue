<script setup>
// New agreement form. FUNCTIONAL ONLY — design pass later.
// Rate & bond are entered in AUD dollars and converted to integer cents on
// submit (all money is stored as cents). New agreements start as a draft.
import { computed, ref } from 'vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import AppLayout from '@/Layouts/AppLayout.vue';
import { useCurrency } from '@/composables/useCurrency';

const props = defineProps({
    customers: { type: Array, required: true }, // [{ id, name }]
    vehicles: { type: Array, required: true }, // [{ id, registration_number, make, model }]
    types: { type: Array, required: true },
    billingCycles: { type: Array, required: true },
});

const { t } = useI18n();
const page = usePage();
const { toCents } = useCurrency();
const base = computed(() => `/app/${page.props.tenant.slug}/agreements`);

const form = useForm({
    customer_id: '',
    vehicle_id: '',
    type: props.types[0] ?? '',
    billing_cycle: props.billingCycles[0] ?? '',
    billing_cycle_day: '',
    rate: '', // AUD dollars in the input; converted to cents on submit
    bond_amount: '', // AUD dollars
    start_date: '',
    end_date: '',
    notes: '',
});

// Client-side customer filter so the dropdown is "searchable".
const customerFilter = ref('');
const filteredCustomers = computed(() => {
    const q = customerFilter.value.trim().toLowerCase();
    if (q === '') return props.customers;
    return props.customers.filter((c) => c.name.toLowerCase().includes(q));
});

function submit() {
    form
        .transform((data) => ({
            ...data,
            rate: toCents(data.rate),
            bond_amount: data.bond_amount === '' ? 0 : toCents(data.bond_amount),
        }))
        .post(base.value);
}
</script>

<template>
    <AppLayout>
        <Head :title="t('agreement.new_agreement')" />

        <div class="py-10">
            <div class="flex items-center justify-between">
                <h1 class="text-2xl font-semibold">{{ t('agreement.new_agreement') }}</h1>
                <Link :href="base" class="text-sm text-slate-500 hover:underline dark:text-slate-400">
                    {{ t('common.back') }}
                </Link>
            </div>

            <form class="mt-6 grid max-w-2xl grid-cols-1 gap-4 sm:grid-cols-2" @submit.prevent="submit">
                <!-- Customer (searchable) -->
                <div class="sm:col-span-2">
                    <span class="text-sm text-slate-600 dark:text-slate-300">{{ t('agreement.fields.customer') }}</span>
                    <input
                        v-model="customerFilter"
                        type="search"
                        :placeholder="t('agreement.search_placeholder')"
                        class="mt-1 w-full rounded border border-slate-300 px-3 py-2 dark:border-slate-700 dark:bg-slate-900"
                    />
                    <select v-model="form.customer_id" class="mt-2 w-full rounded border border-slate-300 px-3 py-2 dark:border-slate-700 dark:bg-slate-900">
                        <option value="" disabled>{{ t('agreement.select_customer') }}</option>
                        <option v-for="c in filteredCustomers" :key="c.id" :value="c.id">{{ c.name }}</option>
                    </select>
                    <span v-if="form.errors.customer_id" class="text-xs text-red-600">{{ form.errors.customer_id }}</span>
                </div>

                <!-- Vehicle (available only) -->
                <label class="block sm:col-span-2">
                    <span class="text-sm text-slate-600 dark:text-slate-300">{{ t('agreement.fields.vehicle') }}</span>
                    <select v-model="form.vehicle_id" class="mt-1 w-full rounded border border-slate-300 px-3 py-2 dark:border-slate-700 dark:bg-slate-900">
                        <option value="" disabled>{{ t('agreement.select_vehicle') }}</option>
                        <option v-for="v in vehicles" :key="v.id" :value="v.id">
                            {{ v.registration_number }} — {{ [v.make, v.model].filter(Boolean).join(' ') }}
                        </option>
                    </select>
                    <span v-if="vehicles.length === 0" class="text-xs text-amber-600">{{ t('agreement.no_vehicles') }}</span>
                    <span v-if="form.errors.vehicle_id" class="text-xs text-red-600">{{ form.errors.vehicle_id }}</span>
                </label>

                <!-- Type -->
                <label class="block">
                    <span class="text-sm text-slate-600 dark:text-slate-300">{{ t('agreement.fields.type') }}</span>
                    <select v-model="form.type" class="mt-1 w-full rounded border border-slate-300 px-3 py-2 dark:border-slate-700 dark:bg-slate-900">
                        <option v-for="ty in types" :key="ty" :value="ty">{{ t(`agreement.types.${ty}`) }}</option>
                    </select>
                    <span v-if="form.errors.type" class="text-xs text-red-600">{{ form.errors.type }}</span>
                </label>

                <!-- Billing cycle -->
                <label class="block">
                    <span class="text-sm text-slate-600 dark:text-slate-300">{{ t('agreement.fields.billing_cycle') }}</span>
                    <select v-model="form.billing_cycle" class="mt-1 w-full rounded border border-slate-300 px-3 py-2 dark:border-slate-700 dark:bg-slate-900">
                        <option v-for="bc in billingCycles" :key="bc" :value="bc">{{ t(`agreement.billing_cycles.${bc}`) }}</option>
                    </select>
                    <span v-if="form.errors.billing_cycle" class="text-xs text-red-600">{{ form.errors.billing_cycle }}</span>
                </label>

                <!-- Billing cycle day -->
                <label class="block">
                    <span class="text-sm text-slate-600 dark:text-slate-300">{{ t('agreement.fields.billing_cycle_day') }}</span>
                    <input v-model="form.billing_cycle_day" type="text" class="mt-1 w-full rounded border border-slate-300 px-3 py-2 dark:border-slate-700 dark:bg-slate-900" />
                    <span v-if="form.errors.billing_cycle_day" class="text-xs text-red-600">{{ form.errors.billing_cycle_day }}</span>
                </label>

                <!-- Rate (AUD) -->
                <label class="block">
                    <span class="text-sm text-slate-600 dark:text-slate-300">{{ t('agreement.rate_aud') }}</span>
                    <input v-model="form.rate" type="number" step="0.01" min="0.01" class="mt-1 w-full rounded border border-slate-300 px-3 py-2 dark:border-slate-700 dark:bg-slate-900" />
                    <span v-if="form.errors.rate" class="text-xs text-red-600">{{ form.errors.rate }}</span>
                </label>

                <!-- Bond (AUD) -->
                <label class="block">
                    <span class="text-sm text-slate-600 dark:text-slate-300">{{ t('agreement.bond_aud') }}</span>
                    <input v-model="form.bond_amount" type="number" step="0.01" min="0" class="mt-1 w-full rounded border border-slate-300 px-3 py-2 dark:border-slate-700 dark:bg-slate-900" />
                    <span v-if="form.errors.bond_amount" class="text-xs text-red-600">{{ form.errors.bond_amount }}</span>
                </label>

                <!-- Start date -->
                <label class="block">
                    <span class="text-sm text-slate-600 dark:text-slate-300">{{ t('agreement.fields.start_date') }}</span>
                    <input v-model="form.start_date" type="date" class="mt-1 w-full rounded border border-slate-300 px-3 py-2 dark:border-slate-700 dark:bg-slate-900" />
                    <span v-if="form.errors.start_date" class="text-xs text-red-600">{{ form.errors.start_date }}</span>
                </label>

                <!-- End date -->
                <label class="block">
                    <span class="text-sm text-slate-600 dark:text-slate-300">{{ t('agreement.fields.end_date') }}</span>
                    <input v-model="form.end_date" type="date" class="mt-1 w-full rounded border border-slate-300 px-3 py-2 dark:border-slate-700 dark:bg-slate-900" />
                    <span v-if="form.errors.end_date" class="text-xs text-red-600">{{ form.errors.end_date }}</span>
                </label>

                <!-- Notes -->
                <label class="block sm:col-span-2">
                    <span class="text-sm text-slate-600 dark:text-slate-300">{{ t('agreement.fields.notes') }}</span>
                    <textarea v-model="form.notes" rows="3" class="mt-1 w-full rounded border border-slate-300 px-3 py-2 dark:border-slate-700 dark:bg-slate-900" />
                    <span v-if="form.errors.notes" class="text-xs text-red-600">{{ form.errors.notes }}</span>
                </label>

                <div class="sm:col-span-2">
                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="rounded bg-slate-800 px-4 py-2 text-white disabled:opacity-50 dark:bg-slate-200 dark:text-slate-900"
                    >
                        {{ t('common.save') }}
                    </button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
