<script setup>
// Invalid token, wrong tenant, or the company is suspended/cancelled —
// deliberately gives no detail about WHICH of those it is (that would leak
// information to someone probing links).
import { Head } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import PublicDocumentLayout from '@/Layouts/PublicDocumentLayout.vue';

const { t } = useI18n();
</script>

<template>
    <Head :title="t('agreement.signing.unavailable_title')" />

    <PublicDocumentLayout>
        <div class="text-center">
            <h1 class="text-xl font-semibold text-ink-900 dark:text-ink-50">{{ t('agreement.signing.unavailable_title') }}</h1>
            <p class="mt-2 text-sm text-ink-500">{{ t('agreement.signing.unavailable_body') }}</p>
        </div>
    </PublicDocumentLayout>
</template>
