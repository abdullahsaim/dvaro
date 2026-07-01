<script setup>
// New lead form — design-system pass.
// After creation the controller re-renders this page with `generatedLink`; we
// reveal the shareable signed intake link + a copy button. Sending is manual
// for now (Notification module is a later session).
import { computed, ref } from 'vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import Button from '@/Components/UI/Button.vue';
import Input from '@/Components/UI/Input.vue';
import Textarea from '@/Components/UI/Textarea.vue';

const props = defineProps({
    generatedLink: { type: String, default: null },
});

const { t } = useI18n();
const page = usePage();
const base = computed(() => `/app/${page.props.tenant.slug}/leads`);

const form = useForm({
    name: '',
    email: '',
    phone: '',
    address: '',
    licence_number: '',
    emergency_contact_name: '',
    emergency_contact_phone: '',
    rental_start_date: '',
    rental_duration: '',
    notes: '',
    token_expires_at: '',
});

function submit() {
    // preserveState:false so the fresh `generatedLink` prop replaces page state.
    form.post(base.value, { preserveState: false });
}

const copied = ref(false);
async function copyLink() {
    try {
        await navigator.clipboard.writeText(props.generatedLink);
        copied.value = true;
        setTimeout(() => (copied.value = false), 2000);
    } catch {
        // Clipboard API unavailable (insecure context) — user can select manually.
    }
}
</script>

<template>
    <AppLayout>
        <Head :title="t('crm.new_lead')" />

        <PageHeader :title="t('crm.new_lead')">
            <template #actions>
                <Button variant="ghost" @click="router.visit(base)">{{ t('common.back') }}</Button>
            </template>
        </PageHeader>

        <!-- Generated-link panel (shown after a successful create) -->
        <div
            v-if="generatedLink"
            class="max-w-2xl rounded-card border border-success-200 bg-success-50 p-4 dark:border-success-900 dark:bg-success-900/20"
        >
            <h2 class="font-medium text-success-800 dark:text-success-300">{{ t('crm.created_heading') }}</h2>
            <p class="mt-1 text-sm text-success-700 dark:text-success-400">{{ t('crm.intake_link_hint') }}</p>
            <div class="mt-3 flex items-end gap-2">
                <Input :model-value="generatedLink" readonly class="w-full" />
                <Button class="shrink-0" @click="copyLink">{{ copied ? t('crm.copied') : t('crm.copy') }}</Button>
            </div>
            <Link :href="`${base}/create`" class="mt-3 inline-block text-sm font-medium text-success-700 hover:underline dark:text-success-400">
                {{ t('crm.create_another') }}
            </Link>
        </div>

        <form v-else class="grid max-w-2xl grid-cols-1 gap-4 sm:grid-cols-2" @submit.prevent="submit">
            <Input v-model="form.name" :label="t('crm.fields.name')" :error="form.errors.name" />
            <Input v-model="form.email" type="email" :label="t('crm.fields.email')" :error="form.errors.email" />
            <Input v-model="form.phone" :label="t('crm.fields.phone')" :error="form.errors.phone" />
            <Input v-model="form.token_expires_at" type="date" :label="t('crm.fields.token_expires_at')" :error="form.errors.token_expires_at" />
            <Input v-model="form.licence_number" :label="t('crm.fields.licence_number')" :error="form.errors.licence_number" />
            <Input v-model="form.rental_start_date" type="date" :label="t('crm.fields.rental_start_date')" :error="form.errors.rental_start_date" />
            <Input v-model="form.rental_duration" :label="t('crm.fields.rental_duration')" :error="form.errors.rental_duration" />
            <Input v-model="form.emergency_contact_name" :label="t('crm.fields.emergency_contact_name')" :error="form.errors.emergency_contact_name" />
            <Input v-model="form.emergency_contact_phone" :label="t('crm.fields.emergency_contact_phone')" :error="form.errors.emergency_contact_phone" />

            <div class="sm:col-span-2">
                <Textarea v-model="form.address" :rows="2" :label="t('crm.fields.address')" :error="form.errors.address" />
            </div>
            <div class="sm:col-span-2">
                <Textarea v-model="form.notes" :rows="3" :label="t('crm.fields.notes')" :error="form.errors.notes" />
            </div>

            <div class="sm:col-span-2">
                <Button type="submit" :loading="form.processing">{{ t('common.save') }}</Button>
            </div>
        </form>
    </AppLayout>
</template>
