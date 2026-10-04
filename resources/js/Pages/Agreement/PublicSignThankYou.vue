<script setup>
// Read-only state of the SAME public link, shown once the agreement is
// signed (whether signed here remotely or in person at the counter — either
// way, the link the customer was given now lands here instead of a sign
// form). The PDF may still be mid-generation (queued) — pdfReady mirrors the
// same pattern as the staff-side agreement page, never a dead link.
import { Head } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import PublicDocumentLayout from '@/Layouts/PublicDocumentLayout.vue';

defineProps({
    tenantName: { type: String, required: true },
    customerName: { type: String, default: null },
    vehicle: { type: String, default: null },
    signedAt: { type: String, default: null },
    pdfReady: { type: Boolean, default: false },
    downloadUrl: { type: String, required: true },
});

const { t } = useI18n();
</script>

<template>
    <Head :title="t('agreement.signing.thank_you_title')" />

    <PublicDocumentLayout :tenant-name="tenantName">
        <div class="text-center">
            <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-success-100 dark:bg-success-900/40">
                <svg viewBox="0 0 20 20" fill="currentColor" class="h-6 w-6 text-success-600 dark:text-success-400">
                    <path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd" />
                </svg>
            </div>
            <h1 class="mt-4 text-xl font-semibold text-ink-900 dark:text-ink-50">{{ t('agreement.signing.thank_you_title') }}</h1>
            <p class="mt-1 text-sm text-ink-500">{{ t('agreement.signing.thank_you_body') }}</p>

            <dl class="mt-5 space-y-2 text-left text-sm">
                <div class="flex justify-between border-b border-ink-100 pb-2 dark:border-ink-800">
                    <dt class="text-ink-500">{{ t('agreement.signing.customer') }}</dt>
                    <dd class="font-medium text-ink-900 dark:text-ink-50">{{ customerName ?? '—' }}</dd>
                </div>
                <div class="flex justify-between border-b border-ink-100 pb-2 dark:border-ink-800">
                    <dt class="text-ink-500">{{ t('agreement.signing.vehicle') }}</dt>
                    <dd class="font-medium text-ink-900 dark:text-ink-50">{{ vehicle ?? '—' }}</dd>
                </div>
            </dl>

            <div class="mt-6">
                <a
                    v-if="pdfReady"
                    :href="downloadUrl"
                    class="inline-flex h-10 items-center rounded-control bg-ink-900 px-4 text-sm font-medium text-white hover:bg-ink-800 dark:bg-ink-100 dark:text-ink-900 dark:hover:bg-ink-200"
                >
                    {{ t('agreement.signing.download_pdf') }}
                </a>
                <p v-else class="text-sm text-ink-500">{{ t('agreement.signing.pdf_pending') }}</p>
            </div>
        </div>
    </PublicDocumentLayout>
</template>
