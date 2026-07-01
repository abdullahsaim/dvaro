<script setup>
// Edit customer — design-system pass. Blacklist state is NOT part of this form —
// it changes through its own endpoints (Blacklist/UnblacklistCustomerAction) on
// the Show page.
import { computed } from 'vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import Button from '@/Components/UI/Button.vue';
import Input from '@/Components/UI/Input.vue';
import Textarea from '@/Components/UI/Textarea.vue';

const props = defineProps({
    customer: { type: Object, required: true },
});

const { t } = useI18n();
const page = usePage();
const base = computed(() => `/app/${page.props.tenant.slug}/customers`);

// Date casts serialize as ISO ("2026-06-22T00:00:00Z"); <input type="date">
// needs "YYYY-MM-DD".
function toDateInput(value) {
    return value ? String(value).slice(0, 10) : '';
}

const form = useForm({
    name: props.customer.name,
    email: props.customer.email,
    phone: props.customer.phone,
    licence_number: props.customer.licence_number,
    licence_expiry: toDateInput(props.customer.licence_expiry),
    passport_number: props.customer.passport_number ?? '',
    date_of_birth: toDateInput(props.customer.date_of_birth),
    address: props.customer.address ?? '',
    emergency_contact_name: props.customer.emergency_contact_name,
    emergency_contact_phone: props.customer.emergency_contact_phone,
    risk_notes: props.customer.risk_notes ?? '',
});

function submit() {
    form.put(`${base.value}/${props.customer.id}`);
}
</script>

<template>
    <AppLayout>
        <Head :title="t('customer.edit_customer')" />

        <PageHeader :title="t('customer.edit_customer')">
            <template #actions>
                <Button variant="ghost" @click="router.visit(`${base}/${customer.id}`)">{{ t('common.back') }}</Button>
            </template>
        </PageHeader>

        <form class="grid max-w-2xl grid-cols-1 gap-4 sm:grid-cols-2" @submit.prevent="submit">
            <Input v-model="form.name" :label="t('customer.fields.name')" :error="form.errors.name" />
            <Input v-model="form.email" type="email" :label="t('customer.fields.email')" :error="form.errors.email" />
            <Input v-model="form.phone" :label="t('customer.fields.phone')" :error="form.errors.phone" />
            <Input v-model="form.date_of_birth" type="date" :label="t('customer.fields.date_of_birth')" :error="form.errors.date_of_birth" />
            <Input v-model="form.licence_number" :label="t('customer.fields.licence_number')" :error="form.errors.licence_number" />
            <Input v-model="form.licence_expiry" type="date" :label="t('customer.fields.licence_expiry')" :error="form.errors.licence_expiry" />
            <Input v-model="form.passport_number" :label="t('customer.fields.passport_number')" :error="form.errors.passport_number" />
            <Input v-model="form.emergency_contact_name" :label="t('customer.fields.emergency_contact_name')" :error="form.errors.emergency_contact_name" />
            <Input v-model="form.emergency_contact_phone" :label="t('customer.fields.emergency_contact_phone')" :error="form.errors.emergency_contact_phone" />

            <div class="sm:col-span-2">
                <Textarea v-model="form.address" :rows="2" :label="t('customer.fields.address')" :error="form.errors.address" />
            </div>
            <div class="sm:col-span-2">
                <Textarea v-model="form.risk_notes" :rows="3" :label="t('customer.fields.risk_notes')" :error="form.errors.risk_notes" />
            </div>

            <div class="sm:col-span-2">
                <Button type="submit" :loading="form.processing">{{ t('common.save') }}</Button>
            </div>
        </form>
    </AppLayout>
</template>
