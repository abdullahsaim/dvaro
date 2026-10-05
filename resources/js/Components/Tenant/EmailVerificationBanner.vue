<script setup>
// Soft email-verification nudge: shown whenever the signed-in tenant user is
// unverified, dismissible for THIS page view only (a plain local ref, nothing
// persisted) — it reappears on the next navigation/reload rather than being
// gone for good, which is the point: a nudge, not a one-time notice, but
// never a block (TenantDashboardController never checks this; full access
// from the moment of signup per the product decision).
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { EnvelopeIcon, XMarkIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    base: { type: String, required: true },
});

const { t } = useI18n();
const dismissed = ref(false);
const sending = ref(false);

function resend() {
    sending.value = true;
    router.post(`${props.base}/email/resend`, {}, {
        preserveScroll: true,
        onFinish: () => (sending.value = false),
    });
}
</script>

<template>
    <div
        v-if="!dismissed"
        class="flex flex-wrap items-center justify-center gap-3 bg-ink-100 px-4 py-2 text-sm text-ink-700 dark:bg-ink-800 dark:text-ink-200"
    >
        <EnvelopeIcon class="h-4 w-4 shrink-0" />
        <span>{{ t('verification.banner_text') }}</span>
        <button
            type="button"
            class="font-medium underline underline-offset-2 hover:text-ink-900 disabled:opacity-50 dark:hover:text-white"
            :disabled="sending"
            @click="resend"
        >
            {{ sending ? t('verification.sending') : t('verification.resend') }}
        </button>
        <button
            type="button"
            class="ml-1 text-ink-400 hover:text-ink-700 dark:hover:text-ink-200"
            :aria-label="t('verification.dismiss')"
            @click="dismissed = true"
        >
            <XMarkIcon class="h-4 w-4" />
        </button>
    </div>
</template>
