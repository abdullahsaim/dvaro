<script setup>
// Settings → Invoices. Four ready-made layouts, the company's logo and accent
// colour, and the wording around the numbers — with a live preview of the real
// template beside the form.
//
// The preview is the SAME Blade dompdf renders, served as HTML with sample
// data, so what you see here is what the PDF prints. It reloads on save (and on
// demand) rather than on every keystroke: it is a server render, not a toy.
import { computed, ref } from 'vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import Button from '@/Components/UI/Button.vue';
import Input from '@/Components/UI/Input.vue';
import Textarea from '@/Components/UI/Textarea.vue';

const props = defineProps({
    settings: { type: Object, required: true },
    gstRegistered: { type: Boolean, default: true },
    layouts: { type: Array, required: true },
    limits: { type: Object, required: true },
    defaults: { type: Object, required: true },
    logoMaxKb: { type: Number, default: 2048 },
    companyLogoUrl: { type: String, default: null },
    invoiceLogoUrl: { type: String, default: null },
    brandColour: { type: String, default: '#0f172a' },
    hasAbn: { type: Boolean, default: false },
    canManage: { type: Boolean, default: false },
});

const { t } = useI18n();
const page = usePage();
const base = computed(() => `/app/${page.props.tenant.slug}/settings/invoice-template`);

const checkbox = 'h-4 w-4 rounded border-ink-300 accent-ink-900 dark:border-ink-700 dark:accent-ink-100';

const form = useForm({
    layout: props.settings.layout,
    accent_colour: props.settings.accent_colour,
    show_logo: props.settings.show_logo,
    show_company_details: props.settings.show_company_details,
    gst_registered: props.gstRegistered,
    title: props.settings.title ?? '',
    intro: props.settings.intro ?? '',
    payment_instructions: props.settings.payment_instructions ?? '',
    footer_note: props.settings.footer_note ?? '',
    thank_you: props.settings.thank_you ?? '',
});

// Cache-busted so the iframe re-renders after a save instead of showing the
// previous version from the browser cache.
const previewKey = ref(Date.now());
const previewSrc = computed(() => `${base.value}/preview?v=${previewKey.value}`);

function refreshPreview() {
    previewKey.value = Date.now();
}

function submit() {
    form.put(base.value, { preserveScroll: true, onSuccess: refreshPreview });
}

// The colour actually used when the field is left empty.
const effectiveColour = computed(() => form.accent_colour || props.brandColour || '#0f172a');

const logoFile = ref(null);
const uploadingLogo = ref(false);

function uploadLogo(event) {
    const file = event.target.files?.[0];
    if (!file) return;

    uploadingLogo.value = true;
    router.post(`${base.value}/logo`, { logo: file }, {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: refreshPreview,
        onFinish: () => {
            uploadingLogo.value = false;
            if (logoFile.value) logoFile.value.value = '';
        },
    });
}

function removeLogo() {
    router.delete(`${base.value}/logo`, { preserveScroll: true, onSuccess: refreshPreview });
}

// The logo the PDF will actually print: the invoice one, else the company one.
const activeLogo = computed(() => props.invoiceLogoUrl || props.companyLogoUrl);
</script>

<template>
    <AppLayout>
        <Head :title="t('settings.invoice_template.title')" />

        <PageHeader
            :title="t('settings.invoice_template.title')"
            :description="t('settings.invoice_template.intro')"
        >
            <template #actions>
                <Button variant="ghost" @click="router.visit(`/app/${page.props.tenant.slug}/settings`)">
                    {{ t('common.back') }}
                </Button>
            </template>
        </PageHeader>

        <div class="grid grid-cols-1 gap-6 xl:grid-cols-[minmax(0,1fr)_minmax(0,1fr)]">
            <!-- ── The form ───────────────────────────────────────────── -->
            <form class="space-y-4" @submit.prevent="submit">
                <!-- Layout -->
                <div class="rounded-card border border-ink-200 bg-white p-5 shadow-subtle dark:border-ink-800 dark:bg-ink-900">
                    <h2 class="text-sm font-semibold text-ink-700 dark:text-ink-200">{{ t('settings.invoice_template.layout') }}</h2>
                    <p class="mt-1 text-sm text-ink-500">{{ t('settings.invoice_template.layout_hint') }}</p>

                    <div class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-4">
                        <button
                            v-for="layout in layouts"
                            :key="layout"
                            type="button"
                            :disabled="!canManage"
                            class="rounded-control border p-3 text-left transition-colors disabled:cursor-not-allowed"
                            :class="form.layout === layout
                                ? 'border-ink-900 bg-ink-50 dark:border-ink-100 dark:bg-ink-950'
                                : 'border-ink-200 hover:border-ink-400 dark:border-ink-800 dark:hover:border-ink-600'"
                            @click="form.layout = layout"
                        >
                            <!-- A tiny wireframe of each layout, drawn in the accent colour. -->
                            <svg viewBox="0 0 40 52" class="h-16 w-full" aria-hidden="true">
                                <rect x="0" y="0" width="40" height="52" rx="2" class="fill-white dark:fill-ink-800" />
                                <template v-if="layout === 'modern'">
                                    <rect x="0" y="0" width="40" height="11" rx="2" :fill="effectiveColour" />
                                    <rect x="4" y="16" width="14" height="2" class="fill-ink-300" />
                                    <rect x="22" y="16" width="14" height="2" class="fill-ink-300" />
                                </template>
                                <template v-else-if="layout === 'minimal'">
                                    <rect x="6" y="6" width="12" height="2" :fill="effectiveColour" />
                                    <rect x="6" y="14" width="18" height="5" :fill="effectiveColour" opacity="0.7" />
                                </template>
                                <template v-else-if="layout === 'compact'">
                                    <rect x="3" y="4" width="10" height="3" :fill="effectiveColour" />
                                    <rect x="15" y="4" width="10" height="7" class="fill-ink-200" />
                                    <rect x="27" y="4" width="10" height="7" class="fill-ink-200" />
                                    <rect x="3" y="14" width="34" height="3" :fill="effectiveColour" />
                                </template>
                                <template v-else>
                                    <rect x="4" y="5" width="12" height="4" class="fill-ink-300" />
                                    <rect x="24" y="5" width="12" height="3" :fill="effectiveColour" />
                                    <rect x="4" y="16" width="32" height="1" class="fill-ink-200" />
                                </template>
                                <rect v-for="n in 4" :key="n" x="4" :y="22 + n * 5" width="32" height="2" class="fill-ink-200" />
                                <rect x="22" y="47" width="14" height="2" :fill="effectiveColour" opacity="0.6" />
                            </svg>
                            <span class="mt-2 block text-sm font-medium text-ink-800 dark:text-ink-100">
                                {{ t(`settings.invoice_template.layouts.${layout}`) }}
                            </span>
                            <span class="block text-xs text-ink-400">{{ t(`settings.invoice_template.layout_hints.${layout}`) }}</span>
                        </button>
                    </div>
                </div>

                <!-- Branding -->
                <div class="rounded-card border border-ink-200 bg-white p-5 shadow-subtle dark:border-ink-800 dark:bg-ink-900">
                    <h2 class="text-sm font-semibold text-ink-700 dark:text-ink-200">{{ t('settings.invoice_template.branding') }}</h2>

                    <div class="mt-4 flex flex-wrap items-center gap-4">
                        <div class="flex h-16 w-32 items-center justify-center rounded-control border border-dashed border-ink-200 bg-ink-50 dark:border-ink-700 dark:bg-ink-950">
                            <img v-if="activeLogo" :src="activeLogo" alt="" class="max-h-14 max-w-28 object-contain" />
                            <span v-else class="text-xs text-ink-400">{{ t('settings.invoice_template.no_logo') }}</span>
                        </div>

                        <div class="text-sm">
                            <p class="text-ink-600 dark:text-ink-300">
                                {{ invoiceLogoUrl
                                    ? t('settings.invoice_template.logo_invoice')
                                    : (companyLogoUrl ? t('settings.invoice_template.logo_company') : t('settings.invoice_template.logo_none')) }}
                            </p>
                            <div v-if="canManage" class="mt-2 flex items-center gap-3">
                                <label class="cursor-pointer text-sm font-medium text-ink-900 underline underline-offset-4 dark:text-ink-100">
                                    {{ uploadingLogo ? t('common.uploading') : t('settings.invoice_template.upload_logo') }}
                                    <input ref="logoFile" type="file" class="sr-only" accept="image/*" @change="uploadLogo" />
                                </label>
                                <button
                                    v-if="invoiceLogoUrl"
                                    type="button"
                                    class="text-sm text-danger-600 underline underline-offset-4"
                                    @click="removeLogo"
                                >
                                    {{ t('settings.invoice_template.remove_logo') }}
                                </button>
                            </div>
                            <p class="mt-1 text-xs text-ink-400">{{ t('settings.invoice_template.logo_hint', { kb: logoMaxKb }) }}</p>
                        </div>
                    </div>

                    <div class="mt-5 flex flex-wrap items-end gap-4">
                        <div>
                            <label class="block text-sm text-ink-600 dark:text-ink-300" for="accent">
                                {{ t('settings.invoice_template.accent') }}
                            </label>
                            <div class="mt-1 flex items-center gap-2">
                                <input
                                    id="accent"
                                    :value="effectiveColour"
                                    type="color"
                                    :disabled="!canManage"
                                    class="h-9 w-14 cursor-pointer rounded-control border border-ink-200 bg-white dark:border-ink-700 dark:bg-ink-900"
                                    @input="form.accent_colour = $event.target.value"
                                />
                                <button
                                    v-if="form.accent_colour"
                                    type="button"
                                    class="text-xs text-ink-500 underline underline-offset-4"
                                    @click="form.accent_colour = null"
                                >
                                    {{ t('settings.invoice_template.use_brand_colour') }}
                                </button>
                            </div>
                            <p v-if="form.errors.accent_colour" class="mt-1 text-xs text-danger-600">{{ form.errors.accent_colour }}</p>
                        </div>
                    </div>

                    <label class="mt-4 flex items-center gap-3">
                        <input v-model="form.show_logo" type="checkbox" :class="checkbox" :disabled="!canManage" />
                        <span class="text-sm text-ink-600 dark:text-ink-300">{{ t('settings.invoice_template.show_logo') }}</span>
                    </label>
                    <label class="mt-3 flex items-center gap-3">
                        <input v-model="form.show_company_details" type="checkbox" :class="checkbox" :disabled="!canManage" />
                        <span class="text-sm text-ink-600 dark:text-ink-300">{{ t('settings.invoice_template.show_company_details') }}</span>
                    </label>
                </div>

                <!-- GST -->
                <div class="rounded-card border border-ink-200 bg-white p-5 shadow-subtle dark:border-ink-800 dark:bg-ink-900">
                    <h2 class="text-sm font-semibold text-ink-700 dark:text-ink-200">{{ t('settings.invoice_template.gst') }}</h2>
                    <p class="mt-1 text-sm text-ink-500">{{ t('settings.invoice_template.gst_hint') }}</p>

                    <label class="mt-4 flex items-center gap-3">
                        <input v-model="form.gst_registered" type="checkbox" :class="checkbox" :disabled="!canManage" />
                        <span class="text-sm text-ink-600 dark:text-ink-300">{{ t('settings.invoice_template.gst_registered') }}</span>
                    </label>

                    <p v-if="form.gst_registered && !hasAbn" class="mt-3 text-xs text-warning-700 dark:text-warning-500">
                        {{ t('settings.invoice_template.abn_missing') }}
                    </p>
                </div>

                <!-- Wording -->
                <div class="rounded-card border border-ink-200 bg-white p-5 shadow-subtle dark:border-ink-800 dark:bg-ink-900">
                    <h2 class="text-sm font-semibold text-ink-700 dark:text-ink-200">{{ t('settings.invoice_template.wording') }}</h2>
                    <p class="mt-1 text-sm text-ink-500">{{ t('settings.invoice_template.wording_hint') }}</p>

                    <div class="mt-4 space-y-4">
                        <Input
                            v-model="form.title"
                            :disabled="!canManage"
                            :maxlength="limits.title"
                            :label="t('settings.invoice_template.doc_title')"
                            :placeholder="gstRegistered ? 'Tax Invoice' : 'Invoice'"
                            :error="form.errors.title"
                        />
                        <Textarea
                            v-model="form.intro"
                            :disabled="!canManage"
                            :maxlength="limits.intro"
                            :rows="2"
                            :label="t('settings.invoice_template.intro_text')"
                            :error="form.errors.intro"
                        />
                        <Textarea
                            v-model="form.payment_instructions"
                            :disabled="!canManage"
                            :maxlength="limits.payment_instructions"
                            :rows="4"
                            :label="t('settings.invoice_template.payment_instructions')"
                            :help="t('settings.invoice_template.payment_instructions_help')"
                            :error="form.errors.payment_instructions"
                        />
                        <Textarea
                            v-model="form.footer_note"
                            :disabled="!canManage"
                            :maxlength="limits.footer_note"
                            :rows="2"
                            :label="t('settings.invoice_template.footer_note')"
                            :error="form.errors.footer_note"
                        />
                        <Input
                            v-model="form.thank_you"
                            :disabled="!canManage"
                            :maxlength="limits.thank_you"
                            :label="t('settings.invoice_template.thank_you')"
                            :error="form.errors.thank_you"
                        />
                    </div>
                </div>

                <div v-if="canManage" class="flex items-center gap-3">
                    <Button type="submit" :loading="form.processing">{{ t('common.save') }}</Button>
                    <span class="text-xs text-ink-400">{{ t('settings.invoice_template.applies_hint') }}</span>
                </div>
                <p v-else class="text-sm text-ink-400">{{ t('settings.invoice_template.read_only') }}</p>
            </form>

            <!-- ── The preview ────────────────────────────────────────── -->
            <div class="xl:sticky xl:top-6 xl:self-start">
                <div class="flex items-center justify-between">
                    <h2 class="text-sm font-semibold text-ink-700 dark:text-ink-200">{{ t('settings.invoice_template.preview') }}</h2>
                    <button type="button" class="text-xs text-ink-500 underline underline-offset-4" @click="refreshPreview">
                        {{ t('settings.invoice_template.refresh_preview') }}
                    </button>
                </div>
                <p class="mt-1 text-xs text-ink-400">{{ t('settings.invoice_template.preview_hint') }}</p>

                <!-- White always: this is a sheet of paper, not part of the app chrome. -->
                <div class="mt-3 overflow-hidden rounded-card border border-ink-200 bg-white shadow-subtle dark:border-ink-800">
                    <iframe
                        :key="previewKey"
                        :src="previewSrc"
                        class="h-[820px] w-full border-0 bg-white"
                        :title="t('settings.invoice_template.preview')"
                    />
                </div>
            </div>
        </div>
    </AppLayout>
</template>
