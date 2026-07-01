<script setup>
// New customer form — design-system pass.
import { computed } from 'vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import Button from '@/Components/UI/Button.vue';
import Input from '@/Components/UI/Input.vue';
import Textarea from '@/Components/UI/Textarea.vue';

const { t } = useI18n();
const page = usePage();
const base = computed(() => `/app/${page.props.tenant.slug}/customers`);

const form = useForm({
    name: '',
    email: '',
    phone: '',
    licence_number: '',
    licence_expiry: '',
    passport_number: '',
    date_of_birth: '',
    address: '',
    emergency_contact_name: '',
    emergency_contact_phone: '',
    risk_notes: '',
});

function submit() {
    form.post(base.value);
}
</script>

<template>
    <AppLayout>
        <Head :title="t('customer.new_customer')" />

        <PageHeader :title="t('customer.new_customer')">
            <template #actions>
                <Button variant="ghost" @click="router.visit(base)">{{ t('common.back') }}</Button>
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
