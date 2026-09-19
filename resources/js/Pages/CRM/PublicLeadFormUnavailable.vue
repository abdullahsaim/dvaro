<script setup>
// Public lead form: link invalid / regenerated / switched off, or too many
// submissions from this connection (reason = 'rate_limited').
import { computed } from 'vue';
import { Head } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import LeadFormLayout from '@/Layouts/LeadFormLayout.vue';

const props = defineProps({
    embedded: { type: Boolean, default: false },
    reason: { type: String, default: 'unavailable' },
});

const { t } = useI18n();

const key = computed(() => (props.reason === 'rate_limited' ? 'rate_limited' : 'unavailable'));
</script>

<template>
    <LeadFormLayout :embedded="embedded">
        <Head :title="t(`crm.public_form.${key}_title`)" />

        <div class="py-6 text-center">
            <h1 class="text-lg font-semibold text-ink-900 dark:text-ink-50">{{ t(`crm.public_form.${key}_title`) }}</h1>
            <p class="mt-2 text-sm text-ink-500">{{ t(`crm.public_form.${key}_body`) }}</p>
        </div>
    </LeadFormLayout>
</template>
