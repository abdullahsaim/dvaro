<script setup>
// Agreement detail. FUNCTIONAL ONLY — design pass later.
// - Sign (draft only): inline HTML5 canvas signature pad → base64 data URL.
// - Create new version (signed/active): posts to the version endpoint.
// - PDF download link once pdf_path is set (generation is queued after signing).
import { computed, ref } from 'vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import AppLayout from '@/Layouts/AppLayout.vue';
import { useCurrency } from '@/composables/useCurrency';

const props = defineProps({
    agreement: { type: Object, required: true },
    versions: { type: Array, default: () => [] },
});

const { t } = useI18n();
const page = usePage();
const { formatAUD } = useCurrency();
const base = computed(() => `/app/${page.props.tenant.slug}/agreements`);
const flash = computed(() => page.props.flash?.success);

const isDraft = computed(() => props.agreement.status === 'draft');
const canVersion = computed(() => ['signed', 'active'].includes(props.agreement.status));

const statusColors = {
    draft: 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300',
    signed: 'bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-300',
    active: 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300',
    completed: 'bg-purple-100 text-purple-800 dark:bg-purple-900/40 dark:text-purple-300',
    cancelled: 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-300',
};
const statusClass = computed(() => statusColors[props.agreement.status] ?? statusColors.draft);

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
</script>

<template>
    <AppLayout>
        <Head :title="t('agreement.agreement_details')" />

        <div class="py-10">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <h1 class="text-2xl font-semibold">
                        {{ t('agreement.agreement_details') }} #{{ agreement.id }}
                    </h1>
                    <span class="text-sm text-slate-500 dark:text-slate-400">v{{ agreement.version }}</span>
                    <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium" :class="statusClass">
                        {{ t(`agreement.statuses.${agreement.status}`) }}
                    </span>
                </div>
                <Link :href="base" class="text-sm text-slate-500 hover:underline dark:text-slate-400">
                    {{ t('common.back') }}
                </Link>
            </div>

            <p
                v-if="flash"
                class="mt-4 rounded border border-green-200 bg-green-50 px-3 py-2 text-sm text-green-800 dark:border-green-900 dark:bg-green-900/30 dark:text-green-300"
            >
                {{ flash }}
            </p>

            <!-- Details -->
            <dl class="mt-6 grid max-w-2xl grid-cols-1 gap-px overflow-hidden rounded border border-slate-200 bg-slate-200 sm:grid-cols-2 dark:border-slate-800 dark:bg-slate-800">
                <div v-for="row in rows" :key="row.label" class="bg-white p-4 dark:bg-slate-950">
                    <dt class="text-sm text-slate-500 dark:text-slate-400">{{ row.label }}</dt>
                    <dd class="mt-1 font-medium">{{ row.value || t('common.none') }}</dd>
                </div>
            </dl>

            <!-- PDF -->
            <div class="mt-6 max-w-2xl rounded border border-slate-200 p-4 dark:border-slate-800">
                <a
                    v-if="agreement.pdf_path"
                    :href="`${base}/${agreement.id}/pdf`"
                    class="text-sm text-indigo-600 hover:underline dark:text-indigo-400"
                >
                    {{ t('agreement.download_pdf') }}
                </a>
                <p v-else class="text-sm text-slate-500 dark:text-slate-400">{{ t('agreement.pdf_pending') }}</p>
            </div>

            <!-- Sign (draft only) -->
            <div v-if="isDraft" class="mt-6 max-w-2xl rounded border border-slate-200 p-4 dark:border-slate-800">
                <h2 class="text-lg font-semibold">{{ t('agreement.sign_heading') }}</h2>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ t('agreement.sign_hint') }}</p>
                <canvas
                    ref="canvas"
                    width="480"
                    height="180"
                    class="mt-3 w-full max-w-[480px] touch-none rounded border border-slate-300 bg-white dark:border-slate-700"
                    @mousedown="start"
                    @mousemove="move"
                    @mouseup="stop"
                    @mouseleave="stop"
                    @touchstart="start"
                    @touchmove="move"
                    @touchend="stop"
                ></canvas>
                <span v-if="signForm.errors.signature_data" class="mt-1 block text-xs text-red-600">{{ signForm.errors.signature_data }}</span>
                <div class="mt-3 flex gap-3">
                    <button
                        type="button"
                        :disabled="!hasDrawn || signForm.processing"
                        class="rounded bg-slate-800 px-4 py-2 text-sm text-white disabled:opacity-50 dark:bg-slate-200 dark:text-slate-900"
                        @click="sign"
                    >
                        {{ t('agreement.sign') }}
                    </button>
                    <button
                        type="button"
                        class="rounded bg-slate-100 px-4 py-2 text-sm text-slate-700 hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700"
                        @click="clearPad"
                    >
                        {{ t('agreement.clear') }}
                    </button>
                </div>
            </div>

            <!-- Signed signature preview + create new version -->
            <div v-else class="mt-6 max-w-2xl rounded border border-slate-200 p-4 dark:border-slate-800">
                <h2 class="text-lg font-semibold">{{ t('agreement.signature') }}</h2>
                <img
                    v-if="agreement.signature_data"
                    :src="agreement.signature_data"
                    alt="signature"
                    class="mt-3 max-w-[480px] rounded border border-slate-200 bg-white dark:border-slate-700"
                />
                <div v-if="canVersion" class="mt-4">
                    <p class="text-sm text-slate-500 dark:text-slate-400">{{ t('agreement.create_version_hint') }}</p>
                    <button
                        type="button"
                        class="mt-3 rounded bg-slate-800 px-4 py-2 text-sm text-white dark:bg-slate-200 dark:text-slate-900"
                        @click="createVersion"
                    >
                        {{ t('agreement.create_version') }}
                    </button>
                </div>
            </div>

            <!-- Version history -->
            <div class="mt-6 max-w-2xl">
                <h2 class="text-lg font-semibold">{{ t('agreement.version_history') }}</h2>
                <ul class="mt-3 divide-y divide-slate-100 rounded border border-slate-200 dark:divide-slate-800 dark:border-slate-800">
                    <li
                        v-for="v in versions"
                        :key="v.id"
                        class="flex items-center justify-between px-4 py-3 text-sm"
                        :class="v.id === agreement.id ? 'bg-slate-50 dark:bg-slate-900' : ''"
                    >
                        <span>
                            {{ t('agreement.version_label', { version: v.version }) }}
                            <span class="ml-2 inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium" :class="statusColors[v.status] ?? statusColors.draft">
                                {{ t(`agreement.statuses.${v.status}`) }}
                            </span>
                            <span v-if="v.id === agreement.id" class="ml-2 text-xs text-slate-400">({{ t('agreement.current_version') }})</span>
                        </span>
                        <Link
                            v-if="v.id !== agreement.id"
                            :href="`${base}/${v.id}`"
                            class="text-indigo-600 hover:underline dark:text-indigo-400"
                        >
                            {{ t('agreement.view_version') }}
                        </Link>
                    </li>
                </ul>
            </div>
        </div>
    </AppLayout>
</template>
