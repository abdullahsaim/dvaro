<script setup>
// New agreement form — design-system pass.
// Rate & bond are entered in AUD dollars and converted to integer cents on
// submit (all money is stored as cents). New agreements start as a draft.
import { computed, ref } from 'vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import Button from '@/Components/UI/Button.vue';
import Input from '@/Components/UI/Input.vue';
import Select from '@/Components/UI/Select.vue';
import Textarea from '@/Components/UI/Textarea.vue';
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

        <PageHeader :title="t('agreement.new_agreement')">
            <template #actions>
                <Button variant="ghost" @click="router.visit(base)">{{ t('common.back') }}</Button>
            </template>
        </PageHeader>

        <form class="grid max-w-2xl grid-cols-1 gap-4 sm:grid-cols-2" @submit.prevent="submit">
            <!-- Customer (searchable) -->
            <div class="space-y-2 sm:col-span-2">
                <Input v-model="customerFilter" type="search" :label="t('agreement.fields.customer')" :placeholder="t('agreement.search_placeholder')" />
                <Select v-model="form.customer_id" :error="form.errors.customer_id">
                    <option value="" disabled>{{ t('agreement.select_customer') }}</option>
                    <option v-for="c in filteredCustomers" :key="c.id" :value="c.id">{{ c.name }}</option>
                </Select>
            </div>

            <!-- Vehicle (available only) -->
            <div class="sm:col-span-2">
                <Select
                    v-model="form.vehicle_id"
                    :label="t('agreement.fields.vehicle')"
                    :error="form.errors.vehicle_id"
                    :help="vehicles.length === 0 ? t('agreement.no_vehicles') : ''"
                >
                    <option value="" disabled>{{ t('agreement.select_vehicle') }}</option>
                    <option v-for="v in vehicles" :key="v.id" :value="v.id">
                        {{ v.registration_number }} — {{ [v.make, v.model].filter(Boolean).join(' ') }}
                    </option>
                </Select>
            </div>

            <Select v-model="form.type" :label="t('agreement.fields.type')" :error="form.errors.type">
                <option v-for="ty in types" :key="ty" :value="ty">{{ t(`agreement.types.${ty}`) }}</option>
            </Select>

            <Select v-model="form.billing_cycle" :label="t('agreement.fields.billing_cycle')" :error="form.errors.billing_cycle">
                <option v-for="bc in billingCycles" :key="bc" :value="bc">{{ t(`agreement.billing_cycles.${bc}`) }}</option>
            </Select>

            <Input v-model="form.billing_cycle_day" :label="t('agreement.fields.billing_cycle_day')" :error="form.errors.billing_cycle_day" />
            <Input v-model="form.rate" type="number" step="0.01" min="0.01" :label="t('agreement.rate_aud')" :error="form.errors.rate" />
            <Input v-model="form.bond_amount" type="number" step="0.01" min="0" :label="t('agreement.bond_aud')" :error="form.errors.bond_amount" />
            <Input v-model="form.start_date" type="date" :label="t('agreement.fields.start_date')" :error="form.errors.start_date" />
            <Input v-model="form.end_date" type="date" :label="t('agreement.fields.end_date')" :error="form.errors.end_date" />

            <div class="sm:col-span-2">
                <Textarea v-model="form.notes" :rows="3" :label="t('agreement.fields.notes')" :error="form.errors.notes" />
            </div>

            <div class="sm:col-span-2">
                <Button type="submit" :loading="form.processing">{{ t('common.save') }}</Button>
            </div>
        </form>
    </AppLayout>
</template>
