<script setup>
// Agreement detail — design-system pass.
// - Sign (draft only): inline HTML5 canvas signature pad → base64 data URL.
// - Create new version (signed/active): posts to the version endpoint.
// - PDF download link once pdf_path is set (generation is queued after signing).
import { computed, ref } from 'vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import StatusBadge from '@/Components/UI/StatusBadge.vue';
import Button from '@/Components/UI/Button.vue';
import Input from '@/Components/UI/Input.vue';
import Select from '@/Components/UI/Select.vue';
import SignatureCanvas from '@/Components/Agreement/SignatureCanvas.vue';
import { useCurrency } from '@/composables/useCurrency';

const props = defineProps({
    agreement: { type: Object, required: true },
    versions: { type: Array, default: () => [] },
    // FROZEN terms: sanitised on save, merge fields already filled in.
    terms: { type: String, default: null },
    termsSource: { type: Object, default: null }, // { name, revision }
    availableVehicles: { type: Array, default: () => [] },
    canRebuildPdf: { type: Boolean, default: false },
    pdfReady: { type: Boolean, default: false },
    canSendForSigning: { type: Boolean, default: false },
    whatsappEnabled: { type: Boolean, default: false },
});

const { t } = useI18n();
const page = usePage();
const { formatAUD } = useCurrency();
const base = computed(() => `/app/${page.props.tenant.slug}/agreements`);

const isDraft = computed(() => props.agreement.status === 'draft');
const canVersion = computed(() => ['signed', 'active'].includes(props.agreement.status));

// agreement status enum → generic StatusBadge variant.
const statusVariants = {
    draft: 'neutral',
    signed: 'info',
    active: 'success',
    completed: 'neutral',
    cancelled: 'danger',
};

function toDate(value) {
    return value ? String(value).slice(0, 10) : t('common.none');
}

const rows = computed(() => [
    { label: t('agreement.fields.customer'), value: props.agreement.customer?.name },
    { label: t('agreement.fields.vehicle'), value: props.agreement.vehicle?.registration_number },
    { label: t('agreement.fields.type'), value: t(`agreement.types.${props.agreement.type}`) },
    { label: t('agreement.fields.billing_cycle'), value: t(`agreement.billing_cycles.${props.agreement.billing_cycle}`) },
    { label: t('agreement.fields.billing_cycle_day'), value: props.agreement.billing_cycle_day },
    { label: t('agreement.fields.rate'), value: formatAUD(props.agreement.rate) },
    { label: t('agreement.fields.bond_amount'), value: formatAUD(props.agreement.bond_amount) },
    { label: t('agreement.fields.start_date'), value: toDate(props.agreement.start_date) },
    { label: t('agreement.fields.end_date'), value: props.agreement.end_date ? toDate(props.agreement.end_date) : t('common.none') },
    { label: t('agreement.fields.signed_at'), value: props.agreement.signed_at ? toDate(props.agreement.signed_at) : t('common.none') },
    { label: t('agreement.fields.notes'), value: props.agreement.notes },
]);

// ── Canvas signature pad (shared component) ────────────────────────────────
const signaturePad = ref(null);
const hasDrawn = ref(false);

function onSignatureChange(drawn) {
    hasDrawn.value = drawn;
}

function clearPad() {
    signaturePad.value?.clearPad();
    signForm.clearErrors();
}

// Re-queue a PDF that never generated. Refused server-side if one exists — a
// signed document is never re-rendered, only recovered when absent.
const pdfForm = useForm({});

function rebuildPdf() {
    pdfForm.post(`${base.value}/${props.agreement.id}/pdf`, { preserveScroll: true });
}

const signForm = useForm({ signature_data: '' });

function sign() {
    const dataUrl = signaturePad.value?.dataUrl();
    if (!dataUrl) return;
    signForm.signature_data = dataUrl;
    signForm.post(`${base.value}/${props.agreement.id}/sign`, { preserveScroll: true });
}

// ── Send for remote signing (email / WhatsApp) ─────────────────────────────
// Defaults to whichever contact methods the customer actually has on file.
const sendForm = useForm({
    channels: [
        ...(props.agreement.customer?.email ? ['email'] : []),
        ...(props.whatsappEnabled && props.agreement.customer?.phone ? ['whatsapp'] : []),
    ],
});

function sendForSigning() {
    sendForm.post(`${base.value}/${props.agreement.id}/send-for-signing`, { preserveScroll: true });
}

// ── Create new version ────────────────────────────────────────────────────
function createVersion() {
    router.post(`${base.value}/${props.agreement.id}/version`, {}, { preserveScroll: true });
}

// ── Change vehicle (two-step: preview → confirm) ────────────────────────────
// Step 1 posts with confirm=false → server runs ProrationService::calculate
// ONLY (pure, no writes) and flashes the split into proration_preview.
// Step 2 posts with confirm=true → server runs VehicleChangeService::execute.
const showChangeForm = ref(false);
const changeForm = useForm({ new_vehicle_id: '', change_date: '', confirm: false });

// The preview is delivered via flash; it applies only to THIS agreement.
const preview = computed(() => {
    const p = page.props.flash?.proration_preview;
    return p && Number(p.agreement_id) === Number(props.agreement.id) ? p : null;
});

function previewChange() {
    changeForm.confirm = false;
    changeForm.post(`${base.value}/${props.agreement.id}/change-vehicle`, { preserveScroll: true });
}

function confirmChange() {
    if (!preview.value) return;
    // Reuse the exact inputs the preview was computed from.
    changeForm.new_vehicle_id = preview.value.new_vehicle_id;
    changeForm.change_date = preview.value.change_date;
    changeForm.confirm = true;
    changeForm.post(`${base.value}/${props.agreement.id}/change-vehicle`, { preserveScroll: true });
}

function cancelChange() {
    showChangeForm.value = false;
    changeForm.reset();
}
</script>

<template>
    <AppLayout>
        <Head :title="t('agreement.agreement_details')" />

        <PageHeader>
            <template #title>
                <span class="flex flex-wrap items-center gap-3">
                    {{ t('agreement.agreement_details') }} #{{ agreement.id }}
                    <span class="text-sm font-normal text-ink-500">v{{ agreement.version }}</span>
                    <StatusBadge :variant="statusVariants[agreement.status]" :label="t(`agreement.statuses.${agreement.status}`)" />
                </span>
            </template>
            <template #actions>
                <Button variant="ghost" @click="router.visit(base)">{{ t('common.back') }}</Button>
            </template>
        </PageHeader>

        <div class="grid max-w-2xl grid-cols-1 gap-6">
            <!-- Details -->
            <dl class="grid grid-cols-1 gap-px overflow-hidden rounded-card border border-ink-200 bg-ink-200 sm:grid-cols-2 dark:border-ink-800 dark:bg-ink-800">
                <div v-for="row in rows" :key="row.label" class="bg-white p-4 dark:bg-ink-900">
                    <dt class="text-sm text-ink-500">{{ row.label }}</dt>
                    <dd class="mt-1 font-medium text-ink-900 dark:text-ink-50">{{ row.value || t('common.none') }}</dd>
                </div>
            </dl>

            <!-- PDF -->
            <div class="rounded-card border border-ink-200 bg-white p-4 shadow-subtle dark:border-ink-800 dark:bg-ink-900">
                <!-- pdf_path being set does not mean the file exists — the
                     server checks that too (PdfAvailability) and this link
                     only renders when it is genuinely there. -->
                <a
                    v-if="pdfReady"
                    :href="`${base}/${agreement.id}/pdf`"
                    class="text-sm font-medium text-ink-900 hover:underline dark:text-ink-100"
                >
                    {{ t('agreement.download_pdf') }}
                </a>
                <div v-else class="flex flex-wrap items-center gap-3">
                    <p class="text-sm text-ink-500">{{ t('agreement.pdf_pending') }}</p>
                    <!-- Recovery: the PDF is queued, so it never arrives if no
                         worker was running when this was signed. -->
                    <button
                        v-if="canRebuildPdf"
                        type="button"
                        class="text-sm font-medium text-ink-900 underline underline-offset-4 disabled:opacity-50 dark:text-ink-100"
                        :disabled="pdfForm.processing"
                        @click="rebuildPdf"
                    >
                        {{ pdfForm.processing ? t('agreement.pdf_rebuilding') : t('agreement.rebuild_pdf') }}
                    </button>
                </div>
            </div>

            <!-- Send for remote signing (draft only) — an alternative to the
                 in-person canvas below, not a replacement for it. -->
            <div v-if="isDraft && canSendForSigning" class="rounded-card border border-ink-200 bg-white p-4 shadow-subtle dark:border-ink-800 dark:bg-ink-900">
                <h2 class="text-lg font-semibold text-ink-900 dark:text-ink-50">{{ t('agreement.send_for_signing_heading') }}</h2>
                <p class="mt-1 text-sm text-ink-500">{{ t('agreement.send_for_signing_hint') }}</p>

                <div class="mt-3 flex flex-wrap gap-4">
                    <label class="flex items-center gap-2 text-sm text-ink-700 dark:text-ink-200">
                        <input
                            v-model="sendForm.channels"
                            type="checkbox"
                            value="email"
                            :disabled="!agreement.customer?.email"
                            class="h-4 w-4 rounded border-ink-300 text-ink-900 focus:ring-ink-400"
                        />
                        {{ t('agreement.channel_email') }}
                        <span v-if="!agreement.customer?.email" class="text-xs text-ink-400">({{ t('agreement.no_email_on_file') }})</span>
                    </label>
                    <label v-if="whatsappEnabled" class="flex items-center gap-2 text-sm text-ink-700 dark:text-ink-200">
                        <input
                            v-model="sendForm.channels"
                            type="checkbox"
                            value="whatsapp"
                            :disabled="!agreement.customer?.phone"
                            class="h-4 w-4 rounded border-ink-300 text-ink-900 focus:ring-ink-400"
                        />
                        {{ t('agreement.channel_whatsapp') }}
                        <span v-if="!agreement.customer?.phone" class="text-xs text-ink-400">({{ t('agreement.no_phone_on_file') }})</span>
                    </label>
                </div>
                <span v-if="sendForm.errors.channels" class="mt-1 block text-sm text-danger-600 dark:text-danger-500">{{ sendForm.errors.channels }}</span>

                <div class="mt-3 flex items-center gap-3">
                    <Button
                        :disabled="sendForm.channels.length === 0"
                        :loading="sendForm.processing"
                        variant="secondary"
                        @click="sendForSigning"
                    >
                        {{ agreement.signing_sent_at ? t('agreement.resend_signing_link') : t('agreement.send_signing_link') }}
                    </Button>
                    <span v-if="agreement.signing_sent_at" class="text-xs text-ink-400">
                        {{ t('agreement.last_sent', { when: toDate(agreement.signing_sent_at) }) }}
                    </span>
                </div>
            </div>

            <!-- Sign (draft only) -->
            <div v-if="isDraft" class="rounded-card border border-ink-200 bg-white p-4 shadow-subtle dark:border-ink-800 dark:bg-ink-900">
                <h2 class="text-lg font-semibold text-ink-900 dark:text-ink-50">{{ t('agreement.sign_heading') }}</h2>
                <p class="mt-1 text-sm text-ink-500">{{ t('agreement.sign_hint') }}</p>
                <SignatureCanvas ref="signaturePad" class="mt-3" @change="onSignatureChange" />
                <span v-if="signForm.errors.signature_data" class="mt-1 block text-sm text-danger-600 dark:text-danger-500">{{ signForm.errors.signature_data }}</span>
                <div class="mt-3 flex gap-3">
                    <Button :disabled="!hasDrawn" :loading="signForm.processing" @click="sign">{{ t('agreement.sign') }}</Button>
                    <Button variant="secondary" @click="clearPad">{{ t('agreement.clear') }}</Button>
                </div>
            </div>

            <!-- Terms and conditions (frozen at creation) -->
            <section v-if="terms" class="mt-8">
                <div class="flex flex-wrap items-baseline justify-between gap-2">
                    <h2 class="text-lg font-semibold text-ink-900 dark:text-ink-50">{{ t('agreement.terms') }}</h2>
                    <p v-if="termsSource" class="text-xs text-ink-400">
                        {{ t('agreement.terms_from', { name: termsSource.name ?? '—', revision: termsSource.revision ?? 1 }) }}
                    </p>
                </div>
                <!-- eslint-disable-next-line vue/no-v-html -->
                <div
                    class="terms-content mt-3 rounded-card border border-ink-200 bg-white p-5 shadow-subtle dark:border-ink-800 dark:bg-ink-900"
                    v-html="terms"
                />
            </section>

            <!-- Signed signature preview + create new version -->
            <div v-else class="rounded-card border border-ink-200 bg-white p-4 shadow-subtle dark:border-ink-800 dark:bg-ink-900">
                <h2 class="text-lg font-semibold text-ink-900 dark:text-ink-50">{{ t('agreement.signature') }}</h2>
                <img
                    v-if="agreement.signature_data"
                    :src="agreement.signature_data"
                    alt="signature"
                    class="mt-3 max-w-[480px] rounded-control border border-ink-200 bg-white dark:border-ink-700"
                />
                <div v-if="canVersion" class="mt-4">
                    <p class="text-sm text-ink-500">{{ t('agreement.create_version_hint') }}</p>
                    <Button class="mt-3" @click="createVersion">{{ t('agreement.create_version') }}</Button>
                </div>
            </div>

            <!-- Change vehicle (signed/active only) — two-step preview/confirm -->
            <div v-if="canVersion" class="rounded-card border border-ink-200 bg-white p-4 shadow-subtle dark:border-ink-800 dark:bg-ink-900">
                <div class="flex items-center justify-between">
                    <h2 class="text-lg font-semibold text-ink-900 dark:text-ink-50">{{ t('agreement.change_vehicle') }}</h2>
                    <Button v-if="!showChangeForm" variant="secondary" size="sm" @click="showChangeForm = true">
                        {{ t('agreement.change_vehicle') }}
                    </Button>
                </div>

                <div v-if="showChangeForm" class="mt-3">
                    <p class="text-sm text-ink-500">{{ t('agreement.change_vehicle_hint') }}</p>

                    <div class="mt-3 grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <Select v-model="changeForm.new_vehicle_id" :label="t('agreement.select_new_vehicle')" :error="changeForm.errors.new_vehicle_id">
                            <option value="" disabled>{{ t('agreement.select_new_vehicle') }}</option>
                            <option v-for="v in availableVehicles" :key="v.id" :value="v.id">
                                {{ v.registration_number }} — {{ v.make }} {{ v.model }}
                            </option>
                        </Select>
                        <Input v-model="changeForm.change_date" type="date" :label="t('agreement.change_date')" :error="changeForm.errors.change_date" />
                    </div>

                    <div class="mt-3 flex gap-3">
                        <Button
                            :disabled="!changeForm.new_vehicle_id || !changeForm.change_date"
                            :loading="changeForm.processing"
                            @click="previewChange"
                        >
                            {{ t('agreement.preview_change') }}
                        </Button>
                        <Button variant="secondary" @click="cancelChange">{{ t('agreement.cancel_change') }}</Button>
                    </div>

                    <!-- Proration preview (no writes have happened yet) -->
                    <div v-if="preview" class="mt-4 rounded-card border border-warning-200 bg-warning-50 p-4 text-sm dark:border-warning-900 dark:bg-warning-900/20">
                        <h3 class="font-semibold text-ink-900 dark:text-ink-50">{{ t('agreement.proration_preview_title') }}</h3>
                        <dl class="mt-2 space-y-1 text-ink-700 dark:text-ink-200">
                            <div class="flex justify-between">
                                <dt>{{ t('agreement.proration_old_vehicle', { days: preview.old_vehicle_days }) }}</dt>
                                <dd class="font-medium tabular-nums">{{ formatAUD(preview.old_vehicle_amount) }}</dd>
                            </div>
                            <div class="flex justify-between">
                                <dt>{{ t('agreement.proration_new_vehicle', { days: preview.new_vehicle_days }) }} — {{ preview.new_vehicle_label }}</dt>
                                <dd class="font-medium tabular-nums">{{ formatAUD(preview.new_vehicle_amount) }}</dd>
                            </div>
                            <div class="flex justify-between border-t border-warning-200 pt-1 font-semibold dark:border-warning-900">
                                <dt>{{ t('agreement.proration_total') }}</dt>
                                <dd class="tabular-nums">{{ formatAUD(preview.old_vehicle_amount + preview.new_vehicle_amount) }}</dd>
                            </div>
                        </dl>
                        <p class="mt-2 text-xs text-warning-800 dark:text-warning-300">{{ t('agreement.proration_note') }}</p>
                        <Button variant="danger" class="mt-3" :loading="changeForm.processing" @click="confirmChange">
                            {{ t('agreement.confirm_change') }}
                        </Button>
                    </div>
                </div>
            </div>

            <!-- Version history -->
            <div>
                <h2 class="text-lg font-semibold text-ink-900 dark:text-ink-50">{{ t('agreement.version_history') }}</h2>
                <ul class="mt-3 divide-y divide-ink-100 overflow-hidden rounded-card border border-ink-200 dark:divide-ink-800 dark:border-ink-800">
                    <li
                        v-for="v in versions"
                        :key="v.id"
                        class="flex items-center justify-between bg-white px-4 py-3 text-sm dark:bg-ink-900"
                        :class="v.id === agreement.id ? 'bg-ink-50 dark:bg-ink-800/50' : ''"
                    >
                        <span class="flex flex-wrap items-center gap-2 text-ink-700 dark:text-ink-200">
                            {{ t('agreement.version_label', { version: v.version }) }}
                            <StatusBadge :variant="statusVariants[v.status]" :label="t(`agreement.statuses.${v.status}`)" />
                            <span v-if="v.id === agreement.id" class="text-xs text-ink-400">({{ t('agreement.current_version') }})</span>
                        </span>
                        <Link
                            v-if="v.id !== agreement.id"
                            :href="`${base}/${v.id}`"
                            class="font-medium text-ink-900 hover:underline dark:text-ink-100"
                        >
                            {{ t('agreement.view_version') }}
                        </Link>
                    </li>
                </ul>
            </div>
        </div>
    </AppLayout>
</template>
