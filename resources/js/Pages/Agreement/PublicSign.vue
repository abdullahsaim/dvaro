<script setup>
// Customer's PUBLIC review-and-sign page. No auth — the token in the URL is
// the only credential. Reuses the same SignatureCanvas component as the
// staff in-person flow (Agreement/Show.vue) so drawing behaves identically.
import { ref } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import PublicDocumentLayout from '@/Layouts/PublicDocumentLayout.vue';
import SignatureCanvas from '@/Components/Agreement/SignatureCanvas.vue';
import Button from '@/Components/UI/Button.vue';
import { useCurrency } from '@/composables/useCurrency';

const props = defineProps({
    tenantName: { type: String, required: true },
    customerName: { type: String, default: null },
    vehicle: { type: String, default: null },
    rate: { type: Number, required: true }, // cents
    billingCycle: { type: String, required: true },
    bondAmount: { type: Number, required: true }, // cents
    startDate: { type: String, default: null },
    endDate: { type: String, default: null },
    terms: { type: String, default: null },
    submitUrl: { type: String, required: true },
});

const { t } = useI18n();
const { formatAUD } = useCurrency();

function toDate(value) {
    return value ? String(value).slice(0, 10) : t('agreement.signing.open_ended');
}

const signaturePad = ref(null);
const hasDrawn = ref(false);

function onSignatureChange(drawn) {
    hasDrawn.value = drawn;
}

const form = useForm({ signature_data: '' });

function submit() {
    const dataUrl = signaturePad.value?.dataUrl();
    if (!dataUrl) return;
    form.signature_data = dataUrl;
    form.post(props.submitUrl, { preserveScroll: true });
}
</script>

<template>
    <Head :title="t('agreement.signing.title')" />

    <PublicDocumentLayout :tenant-name="tenantName">
        <h1 class="text-xl font-semibold text-ink-900 dark:text-ink-50">{{ t('agreement.signing.title') }}</h1>
        <p class="mt-1 text-sm text-ink-500">{{ t('agreement.signing.intro') }}</p>

        <dl class="mt-5 grid grid-cols-1 gap-x-4 gap-y-3 rounded-card border border-ink-200 bg-ink-50/60 p-4 text-sm sm:grid-cols-2 dark:border-ink-800 dark:bg-ink-950/30">
            <div>
                <dt class="text-ink-500">{{ t('agreement.signing.customer') }}</dt>
                <dd class="font-medium text-ink-900 dark:text-ink-50">{{ customerName ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-ink-500">{{ t('agreement.signing.vehicle') }}</dt>
                <dd class="font-medium text-ink-900 dark:text-ink-50">{{ vehicle ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-ink-500">{{ t('agreement.signing.rate') }}</dt>
                <dd class="font-medium text-ink-900 dark:text-ink-50">{{ formatAUD(rate) }} / {{ t(`agreement.billing_cycles.${billingCycle}`) }}</dd>
            </div>
            <div>
                <dt class="text-ink-500">{{ t('agreement.signing.bond') }}</dt>
                <dd class="font-medium text-ink-900 dark:text-ink-50">{{ formatAUD(bondAmount) }}</dd>
            </div>
            <div>
                <dt class="text-ink-500">{{ t('agreement.signing.start_date') }}</dt>
                <dd class="font-medium text-ink-900 dark:text-ink-50">{{ toDate(startDate) }}</dd>
            </div>
            <div>
                <dt class="text-ink-500">{{ t('agreement.signing.end_date') }}</dt>
                <dd class="font-medium text-ink-900 dark:text-ink-50">{{ toDate(endDate) }}</dd>
            </div>
        </dl>

        <section v-if="terms" class="mt-6">
            <h2 class="text-base font-semibold text-ink-900 dark:text-ink-50">{{ t('agreement.signing.terms_heading') }}</h2>
            <!-- eslint-disable-next-line vue/no-v-html -->
            <div
                class="terms-content mt-2 max-h-80 overflow-y-auto rounded-card border border-ink-200 p-4 text-sm dark:border-ink-800"
                v-html="terms"
            />
        </section>

        <section class="mt-6">
            <h2 class="text-base font-semibold text-ink-900 dark:text-ink-50">{{ t('agreement.signing.sign_heading') }}</h2>
            <p class="mt-1 text-sm text-ink-500">{{ t('agreement.signing.sign_hint') }}</p>
            <SignatureCanvas ref="signaturePad" class="mt-3" @change="onSignatureChange" />
            <span v-if="form.errors.signature_data" class="mt-1 block text-sm text-danger-600 dark:text-danger-500">{{ form.errors.signature_data }}</span>
            <div class="mt-3 flex gap-3">
                <Button :disabled="!hasDrawn" :loading="form.processing" @click="submit">{{ t('agreement.signing.sign') }}</Button>
                <Button variant="secondary" @click="signaturePad?.clearPad()">{{ t('agreement.signing.clear') }}</Button>
            </div>
        </section>
    </PublicDocumentLayout>
</template>
