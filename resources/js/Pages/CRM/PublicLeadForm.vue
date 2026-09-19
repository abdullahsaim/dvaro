<script setup>
// Tenant's PUBLIC lead form (share link / QR / website iframe). No auth.
//
// Posts to the CSRF-exempt submit route with: the fields, the encrypted start
// token (human-pacing check), a hidden honeypot, the reCAPTCHA v2 token (when a
// site key is configured), `embedded`, and `ref` (host page URL, from ?ref=).
// Validation errors come back as page props (no session in a cross-site iframe).
import { computed, nextTick, onMounted, ref, watch } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import LeadFormLayout from '@/Layouts/LeadFormLayout.vue';
import Button from '@/Components/UI/Button.vue';
import Input from '@/Components/UI/Input.vue';
import Textarea from '@/Components/UI/Textarea.vue';

const props = defineProps({
    tenantName: { type: String, required: true },
    intro: { type: String, default: '' },
    submitUrl: { type: String, required: true },
    started: { type: String, required: true },
    siteKey: { type: String, default: null },
    embedded: { type: Boolean, default: false },
    honeypot: { type: String, required: true },
    errors: { type: Object, default: () => ({}) },
});

const { t } = useI18n();

const today = new Date().toISOString().slice(0, 10);

const form = useForm({
    name: '',
    phone: '',
    email: '',
    rental_start_date: '',
    rental_duration: '',
    notes: '',
    started: props.started,
    embedded: props.embedded,
    ref: typeof window !== 'undefined' ? new URLSearchParams(window.location.search).get('ref') : null,
    [props.honeypot]: '',
    'g-recaptcha-response': '',
});

// ── reCAPTCHA v2 checkbox ────────────────────────────────────────────────────
const captchaEl = ref(null);
let widgetId = null;

function renderCaptcha() {
    if (!props.siteKey || !captchaEl.value || widgetId !== null || !window.grecaptcha?.render) return;

    widgetId = window.grecaptcha.render(captchaEl.value, {
        sitekey: props.siteKey,
        theme: document.documentElement.classList.contains('dark') ? 'dark' : 'light',
        callback: (token) => (form['g-recaptcha-response'] = token),
        'expired-callback': () => (form['g-recaptcha-response'] = ''),
    });
}

onMounted(() => {
    if (!props.siteKey) return;

    if (window.grecaptcha?.render) {
        renderCaptcha();
        return;
    }

    window.dvaroRecaptchaLoaded = renderCaptcha;
    const script = document.createElement('script');
    script.src = 'https://www.google.com/recaptcha/api.js?onload=dvaroRecaptchaLoaded&render=explicit';
    script.async = true;
    script.defer = true;
    document.head.appendChild(script);
});

function resetCaptcha() {
    form['g-recaptcha-response'] = '';
    if (widgetId !== null) window.grecaptcha?.reset(widgetId);
}

// Server re-render after a rejected submit: errors arrive as a prop.
const formError = computed(() => form.errors.form || form.errors.captcha || '');

watch(() => props.errors, async (errors) => {
    form.clearErrors();
    form.setError(errors ?? {});
    await nextTick();
}, { immediate: true });

const canSubmit = computed(() => !form.processing && (!props.siteKey || form['g-recaptcha-response'] !== ''));

function submit() {
    form.post(props.submitUrl, {
        preserveScroll: true,
        onError: () => resetCaptcha(),
    });
}
</script>

<template>
    <LeadFormLayout :embedded="embedded" :tenant-name="tenantName">
        <Head :title="t('crm.public_form.title', { company: tenantName })" />

        <header>
            <h1 class="text-xl font-semibold tracking-tight text-ink-900 sm:text-2xl dark:text-ink-50">
                {{ t('crm.public_form.heading') }}
            </h1>
            <p class="mt-2 whitespace-pre-line text-sm text-ink-500">
                {{ intro || t('crm.public_form.default_intro', { company: tenantName }) }}
            </p>
        </header>

        <form class="mt-6 grid grid-cols-1 gap-4 sm:grid-cols-2" novalidate @submit.prevent="submit">
            <div class="sm:col-span-2">
                <Input v-model="form.name" required autocomplete="name" :label="t('crm.public_form.name')" :error="form.errors.name" />
            </div>
            <Input v-model="form.phone" type="tel" required autocomplete="tel" inputmode="tel" :label="t('crm.public_form.phone')" :error="form.errors.phone" />
            <Input v-model="form.email" type="email" required autocomplete="email" :label="t('crm.public_form.email')" :error="form.errors.email" />
            <Input v-model="form.rental_start_date" type="date" :min="today" :label="t('crm.public_form.start_date')" :error="form.errors.rental_start_date" />
            <Input v-model="form.rental_duration" :label="t('crm.public_form.duration')" :placeholder="t('crm.public_form.duration_placeholder')" :error="form.errors.rental_duration" />
            <div class="sm:col-span-2">
                <Textarea v-model="form.notes" :rows="3" :label="t('crm.public_form.notes')" :error="form.errors.notes" />
            </div>

            <!-- Honeypot: invisible to people (and screen readers); bots fill it. -->
            <div class="absolute -left-[10000px] h-px w-px overflow-hidden" aria-hidden="true">
                <label>
                    Website
                    <input v-model="form[honeypot]" type="text" tabindex="-1" autocomplete="off" />
                </label>
            </div>

            <div v-if="siteKey" class="sm:col-span-2">
                <div ref="captchaEl" class="min-h-[78px]" />
            </div>

            <Transition
                enter-active-class="transition duration-150 ease-out"
                enter-from-class="opacity-0 -translate-y-1"
                leave-active-class="transition duration-100 ease-in"
                leave-to-class="opacity-0"
            >
                <p
                    v-if="formError"
                    role="alert"
                    class="rounded-control bg-danger-50 px-3 py-2 text-sm text-danger-700 sm:col-span-2 dark:bg-danger-900/40 dark:text-danger-500"
                >
                    {{ formError }}
                </p>
            </Transition>

            <div class="flex flex-col gap-3 sm:col-span-2">
                <Button type="submit" size="lg" class="w-full" :loading="form.processing" :disabled="!canSubmit">
                    {{ t('crm.public_form.submit') }}
                </Button>
                <p class="text-center text-xs text-ink-400">{{ t('crm.public_form.privacy') }}</p>
            </div>
        </form>
    </LeadFormLayout>
</template>
