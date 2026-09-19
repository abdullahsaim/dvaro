<script setup>
// Thank-you page for the tenant's public lead form (also shown to bots that
// tripped the honeypot — they learn nothing).
import { Head } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { CheckIcon } from '@heroicons/vue/24/outline';
import LeadFormLayout from '@/Layouts/LeadFormLayout.vue';

defineProps({
    tenantName: { type: String, required: true },
    embedded: { type: Boolean, default: false },
});

const { t } = useI18n();
</script>

<template>
    <LeadFormLayout :embedded="embedded" :tenant-name="tenantName">
        <Head :title="t('crm.public_form.thanks_title')" />

        <div class="flex flex-col items-center py-6 text-center">
            <div class="flex h-14 w-14 items-center justify-center rounded-full bg-success-100 text-success-700 dark:bg-success-900/40 dark:text-success-500">
                <CheckIcon class="h-7 w-7" />
            </div>
            <h1 class="mt-5 text-xl font-semibold text-ink-900 dark:text-ink-50">{{ t('crm.public_form.thanks_title') }}</h1>
            <p class="mt-2 max-w-sm text-sm text-ink-500">{{ t('crm.public_form.thanks_body', { company: tenantName }) }}</p>
        </div>
    </LeadFormLayout>
</template>
