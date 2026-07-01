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
import { useCurrency } from '@/composables/useCurrency';

const props = defineProps({
    agreement: { type: Object, required: true },
    versions: { type: Array, default: () => [] },
    availableVehicles: { type: Array, default: () => [] },
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

// ── Canvas signature pad ──────────────────────────────────────────────────
const canvas = ref(null);
const hasDrawn = ref(false);
let drawing = false;

function pos(event) {
    const rect = canvas.value.getBoundingClientRect();
    const point = event.touches ? event.touches[0] : event;
    return { x: point.clientX - rect.left, y: point.clientY - rect.top };
}

function start(event) {
    event.preventDefault();
    drawing = true;
    const ctx = canvas.value.getContext('2d');
    const { x, y } = pos(event);
    ctx.beginPath();
    ctx.moveTo(x, y);
}

function move(event) {
    if (!drawing) return;
    event.preventDefault();
    const ctx = canvas.value.getContext('2d');
    const { x, y } = pos(event);
    ctx.lineWidth = 2;
    ctx.lineCap = 'round';
    ctx.strokeStyle = '#0f172a';
    ctx.lineTo(x, y);
    ctx.stroke();
    hasDrawn.value = true;
}

function stop() {
    drawing = false;
}

function clearPad() {
    const ctx = canvas.value.getContext('2d');
    ctx.clearRect(0, 0, canvas.value.width, canvas.value.height);
    hasDrawn.value = false;
    signForm.clearErrors();
}

const signForm = useForm({ signature_data: '' });

function sign() {
    if (!hasDrawn.value) return;
    signForm.signature_data = canvas.value.toDataURL('image/png');
    signForm.post(`${base.value}/${props.agreement.id}/sign`, { preserveScroll: true });
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
                <a
                    v-if="agreement.pdf_path"
                    :href="`${base}/${agreement.id}/pdf`"
                    class="text-sm font-medium text-ink-900 hover:underline dark:text-ink-100"
                >
                    {{ t('agreement.download_pdf') }}
                </a>
                <p v-else class="text-sm text-ink-500">{{ t('agreement.pdf_pending') }}</p>
            </div>

            <!-- Sign (draft only) -->
            <div v-if="isDraft" class="rounded-card border border-ink-200 bg-white p-4 shadow-subtle dark:border-ink-800 dark:bg-ink-900">
                <h2 class="text-lg font-semibold text-ink-900 dark:text-ink-50">{{ t('agreement.sign_heading') }}</h2>
                <p class="mt-1 text-sm text-ink-500">{{ t('agreement.sign_hint') }}</p>
                <canvas
                    ref="canvas"
                    width="480"
                    height="180"
                    class="mt-3 w-full max-w-[480px] touch-none rounded-control border border-ink-300 bg-white dark:border-ink-700"
                    @mousedown="start"
                    @mousemove="move"
                    @mouseup="stop"
                    @mouseleave="stop"
                    @touchstart="start"
                    @touchmove="move"
                    @touchend="stop"
                ></canvas>
                <span v-if="signForm.errors.signature_data" class="mt-1 block text-sm text-danger-600 dark:text-danger-500">{{ signForm.errors.signature_data }}</span>
                <div class="mt-3 flex gap-3">
                    <Button :disabled="!hasDrawn" :loading="signForm.processing" @click="sign">{{ t('agreement.sign') }}</Button>
                    <Button variant="secondary" @click="clearPad">{{ t('agreement.clear') }}</Button>
                </div>
            </div>

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
