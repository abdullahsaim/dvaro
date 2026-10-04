<script setup>
// Shell for public, token-gated document pages opened from an email/WhatsApp
// link (agreement review-and-sign today; any future one-link document page
// can reuse it). Always a normal top-level page — never embedded in an
// iframe, unlike the lead form — so there is no postMessage/resize dance
// here, just a centred card with the company's name and a quiet footer.
import { useI18n } from 'vue-i18n';

defineProps({
    tenantName: { type: String, default: '' },
});

const { t } = useI18n();
</script>

<template>
    <div class="min-h-screen bg-ink-50 px-4 py-10 sm:py-16 dark:bg-ink-950">
        <main class="mx-auto w-full max-w-2xl">
            <p v-if="tenantName" class="text-center text-sm font-medium uppercase tracking-wide text-ink-500">
                {{ tenantName }}
            </p>
            <div class="mt-4 rounded-card border border-ink-200 bg-white p-6 shadow-subtle sm:p-8 dark:border-ink-800 dark:bg-ink-900">
                <slot />
            </div>
            <p class="mt-6 text-center text-xs text-ink-400">{{ t('agreement.signing.powered_by') }}</p>
        </main>
    </div>
</template>
