<script setup>
// CRM → Lead form. One public enquiry form per company: share link, QR code,
// website embed code, "send the link", settings (admin) and link regeneration
// (admin — breaks every old link/QR/embed).
import { computed, ref } from 'vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import {
    ArrowDownTrayIcon,
    ArrowPathIcon,
    ArrowTopRightOnSquareIcon,
    CheckIcon,
    ClipboardDocumentIcon,
    PaperAirplaneIcon,
    ShieldExclamationIcon,
} from '@heroicons/vue/24/outline';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import Button from '@/Components/UI/Button.vue';
import Input from '@/Components/UI/Input.vue';
import Textarea from '@/Components/UI/Textarea.vue';
import Modal from '@/Components/UI/Modal.vue';
import StatusBadge from '@/Components/UI/StatusBadge.vue';

const props = defineProps({
    publicUrl: { type: String, required: true },
    embedSnippet: { type: String, required: true },
    settings: { type: Object, required: true }, // { enabled, intro, allowed_domains[] }
    isAdmin: { type: Boolean, default: false },
    smsEnabled: { type: Boolean, default: false },
    captchaConfigured: { type: Boolean, default: false },
    submissions: { type: Number, default: 0 }, // last 30 days
});

const { t } = useI18n();
const page = usePage();
const base = computed(() => `/app/${page.props.tenant.slug}/leads/form`);

// ── Copy to clipboard (with a brief "Copied" state) ──────────────────────────
const copied = ref(null);

async function copy(key, text) {
    try {
        await navigator.clipboard.writeText(text);
    } catch {
        const el = document.createElement('textarea');
        el.value = text;
        document.body.appendChild(el);
        el.select();
        document.execCommand('copy');
        el.remove();
    }
    copied.value = key;
    setTimeout(() => (copied.value = copied.value === key ? null : copied.value), 1800);
}

// ── Settings (admin) ─────────────────────────────────────────────────────────
const settingsForm = useForm({
    enabled: props.settings.enabled,
    intro: props.settings.intro,
    allowed_domains: [...props.settings.allowed_domains],
});
const newDomain = ref('');

function addDomain() {
    const value = newDomain.value.trim();
    if (!value || settingsForm.allowed_domains.includes(value)) return;
    settingsForm.allowed_domains.push(value);
    newDomain.value = '';
}

function removeDomain(i) {
    settingsForm.allowed_domains.splice(i, 1);
}

function saveSettings() {
    settingsForm.put(base.value, { preserveScroll: true });
}

const domainErrors = computed(() =>
    Object.entries(settingsForm.errors)
        .filter(([k]) => k.startsWith('allowed_domains'))
        .map(([, v]) => v),
);

// ── Regenerate (admin) ───────────────────────────────────────────────────────
const confirmRegenerate = ref(false);
const regenerating = ref(false);

function regenerate() {
    router.post(`${base.value}/regenerate`, {}, {
        preserveScroll: true,
        onStart: () => (regenerating.value = true),
        onFinish: () => {
            regenerating.value = false;
            confirmRegenerate.value = false;
        },
    });
}

// ── Send link ────────────────────────────────────────────────────────────────
const sendOpen = ref(false);
const sendForm = useForm({ channel: 'email', recipient: '' });

function openSend() {
    sendForm.reset();
    sendForm.clearErrors();
    sendOpen.value = true;
}

function send() {
    sendForm.post(`${base.value}/send`, {
        preserveScroll: true,
        onSuccess: () => (sendOpen.value = false),
    });
}

const card = 'rounded-card border border-ink-200 bg-white shadow-subtle dark:border-ink-800 dark:bg-ink-900';
const checkbox = 'h-4 w-4 rounded border-ink-300 accent-ink-900 dark:border-ink-700 dark:accent-ink-100';
// Matches Button variant="ghost" size="sm" — for real links (no <button> in <a>).
const ghostLink = 'inline-flex h-8 items-center gap-2 rounded-control px-3 text-sm font-medium text-ink-700 transition-colors duration-150 hover:bg-ink-100 dark:text-ink-300 dark:hover:bg-ink-800';
</script>

<template>
    <AppLayout>
        <Head :title="t('crm.lead_form.title')" />

        <PageHeader :title="t('crm.lead_form.title')" :description="t('crm.lead_form.intro')">
            <template #actions>
                <Button variant="ghost" @click="router.visit(`/app/${page.props.tenant.slug}/leads`)">{{ t('common.back') }}</Button>
            </template>
        </PageHeader>

        <div class="grid max-w-5xl grid-cols-1 gap-6 lg:grid-cols-3">
            <div class="space-y-6 lg:col-span-2">
                <!-- Status strip -->
                <div class="flex flex-wrap items-center gap-3">
                    <StatusBadge :variant="settings.enabled ? 'success' : 'neutral'" :label="settings.enabled ? t('crm.lead_form.live') : t('crm.lead_form.off')" />
                    <span class="text-sm text-ink-500">{{ t('crm.lead_form.submissions', { n: submissions }) }}</span>
                </div>

                <div
                    v-if="!captchaConfigured"
                    class="flex items-start gap-3 rounded-card border border-warning-100 bg-warning-50 p-4 text-sm text-warning-700 dark:border-warning-900 dark:bg-warning-900/40 dark:text-warning-500"
                >
                    <ShieldExclamationIcon class="mt-0.5 h-5 w-5 shrink-0" />
                    <p>{{ t('crm.lead_form.captcha_missing') }}</p>
                </div>

                <!-- Share link -->
                <section :class="card" class="p-5">
                    <h2 class="text-sm font-semibold text-ink-900 dark:text-ink-50">{{ t('crm.lead_form.share_title') }}</h2>
                    <p class="mt-1 text-sm text-ink-500">{{ t('crm.lead_form.share_hint') }}</p>

                    <div class="mt-4 flex flex-col gap-2 sm:flex-row">
                        <input
                            :value="publicUrl"
                            readonly
                            class="h-10 min-w-0 flex-1 rounded-control border border-ink-200 bg-ink-50 px-3 font-mono text-xs text-ink-700 dark:border-ink-800 dark:bg-ink-950 dark:text-ink-300"
                            @focus="$event.target.select()"
                        />
                        <Button variant="secondary" @click="copy('link', publicUrl)">
                            <CheckIcon v-if="copied === 'link'" class="h-4 w-4" />
                            <ClipboardDocumentIcon v-else class="h-4 w-4" />
                            {{ copied === 'link' ? t('crm.lead_form.copied') : t('crm.lead_form.copy') }}
                        </Button>
                    </div>

                    <div class="mt-3 flex flex-wrap gap-2">
                        <a :href="publicUrl" target="_blank" rel="noopener" :class="ghostLink">
                            <ArrowTopRightOnSquareIcon class="h-4 w-4" />{{ t('crm.lead_form.preview') }}
                        </a>
                        <a :href="`${base}/qr`" :class="ghostLink">
                            <ArrowDownTrayIcon class="h-4 w-4" />{{ t('crm.lead_form.download_qr') }}
                        </a>
                        <Button variant="ghost" size="sm" @click="openSend">
                            <PaperAirplaneIcon class="h-4 w-4" />{{ t('crm.lead_form.send') }}
                        </Button>
                    </div>
                </section>

                <!-- Embed -->
                <section :class="card" class="p-5">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <h2 class="text-sm font-semibold text-ink-900 dark:text-ink-50">{{ t('crm.lead_form.embed_title') }}</h2>
                            <p class="mt-1 text-sm text-ink-500">{{ t('crm.lead_form.embed_hint') }}</p>
                        </div>
                        <Button variant="secondary" size="sm" @click="copy('embed', embedSnippet)">
                            <CheckIcon v-if="copied === 'embed'" class="h-4 w-4" />
                            <ClipboardDocumentIcon v-else class="h-4 w-4" />
                            {{ copied === 'embed' ? t('crm.lead_form.copied') : t('crm.lead_form.copy_code') }}
                        </Button>
                    </div>
                    <pre class="mt-4 max-h-56 overflow-auto rounded-control bg-ink-950 p-4 text-xs leading-relaxed text-ink-100"><code>{{ embedSnippet }}</code></pre>
                </section>
            </div>

            <!-- Settings (admin) -->
            <aside class="space-y-6">
                <section :class="card" class="p-5">
                    <h2 class="text-sm font-semibold text-ink-900 dark:text-ink-50">{{ t('crm.lead_form.settings') }}</h2>
                    <p v-if="!isAdmin" class="mt-1 text-sm text-ink-500">{{ t('crm.lead_form.admin_only') }}</p>

                    <form class="mt-4 space-y-4" :class="isAdmin ? '' : 'pointer-events-none opacity-60'" @submit.prevent="saveSettings">
                        <label class="flex items-center gap-3">
                            <input v-model="settingsForm.enabled" type="checkbox" :class="checkbox" :disabled="!isAdmin" />
                            <span class="text-sm text-ink-700 dark:text-ink-300">{{ t('crm.lead_form.enabled') }}</span>
                        </label>

                        <Textarea
                            v-model="settingsForm.intro"
                            :rows="3"
                            :label="t('crm.lead_form.intro_label')"
                            :placeholder="t('crm.lead_form.intro_placeholder')"
                            :error="settingsForm.errors.intro"
                        />

                        <div>
                            <p class="text-sm font-medium text-ink-700 dark:text-ink-300">{{ t('crm.lead_form.domains') }}</p>
                            <p class="mt-0.5 text-xs text-ink-500">{{ t('crm.lead_form.domains_hint') }}</p>

                            <ul v-if="settingsForm.allowed_domains.length" class="mt-2 space-y-1">
                                <li
                                    v-for="(d, i) in settingsForm.allowed_domains"
                                    :key="d"
                                    class="flex items-center justify-between rounded-control bg-ink-50 px-3 py-1.5 text-sm dark:bg-ink-800"
                                >
                                    <span class="truncate font-mono text-xs text-ink-700 dark:text-ink-200">{{ d }}</span>
                                    <button type="button" class="text-xs text-ink-500 hover:text-danger-600" @click="removeDomain(i)">
                                        {{ t('common.delete') }}
                                    </button>
                                </li>
                            </ul>
                            <p v-else class="mt-2 text-xs text-ink-400">{{ t('crm.lead_form.domains_any') }}</p>

                            <div class="mt-2 flex gap-2">
                                <Input v-model="newDomain" :placeholder="t('crm.lead_form.domain_placeholder')" class="flex-1" @keydown.enter.prevent="addDomain" />
                                <Button type="button" variant="secondary" @click="addDomain">{{ t('crm.lead_form.add') }}</Button>
                            </div>
                            <p v-for="err in domainErrors" :key="err" class="mt-1 text-sm text-danger-600 dark:text-danger-500">{{ err }}</p>
                        </div>

                        <Button type="submit" :loading="settingsForm.processing" :disabled="!isAdmin">{{ t('common.save') }}</Button>
                    </form>
                </section>

                <section v-if="isAdmin" :class="card" class="p-5">
                    <h2 class="text-sm font-semibold text-ink-900 dark:text-ink-50">{{ t('crm.lead_form.regenerate_title') }}</h2>
                    <p class="mt-1 text-sm text-ink-500">{{ t('crm.lead_form.regenerate_hint') }}</p>
                    <Button variant="secondary" class="mt-4" @click="confirmRegenerate = true">
                        <ArrowPathIcon class="h-4 w-4" />{{ t('crm.lead_form.regenerate') }}
                    </Button>
                </section>
            </aside>
        </div>

        <!-- Regenerate confirm -->
        <Modal :show="confirmRegenerate" :title="t('crm.lead_form.regenerate_title')" @close="confirmRegenerate = false">
            <p class="text-sm text-ink-600 dark:text-ink-300">{{ t('crm.lead_form.regenerate_confirm') }}</p>
            <template #footer>
                <Button variant="ghost" @click="confirmRegenerate = false">{{ t('common.cancel') }}</Button>
                <Button variant="danger" :loading="regenerating" @click="regenerate">{{ t('crm.lead_form.regenerate') }}</Button>
            </template>
        </Modal>

        <!-- Send link -->
        <Modal :show="sendOpen" :title="t('crm.lead_form.send_title')" @close="sendOpen = false">
            <form id="send-lead-form-link" class="space-y-4" @submit.prevent="send">
                <div class="inline-flex rounded-control bg-ink-100 p-1 dark:bg-ink-800" role="radiogroup">
                    <button
                        v-for="ch in (smsEnabled ? ['email', 'sms'] : ['email'])"
                        :key="ch"
                        type="button"
                        role="radio"
                        :aria-checked="sendForm.channel === ch"
                        class="rounded-control px-3 py-1.5 text-sm transition-colors"
                        :class="sendForm.channel === ch
                            ? 'bg-white text-ink-900 shadow-subtle dark:bg-ink-950 dark:text-ink-50'
                            : 'text-ink-500 hover:text-ink-900 dark:hover:text-ink-100'"
                        @click="sendForm.channel = ch; sendForm.clearErrors()"
                    >
                        {{ t(`crm.lead_form.channel_${ch}`) }}
                    </button>
                </div>
                <Input
                    v-model="sendForm.recipient"
                    :type="sendForm.channel === 'sms' ? 'tel' : 'email'"
                    :label="sendForm.channel === 'sms' ? t('crm.lead_form.recipient_phone') : t('crm.lead_form.recipient_email')"
                    :error="sendForm.errors.recipient || sendForm.errors.channel"
                />
                <p v-if="!smsEnabled" class="text-xs text-ink-400">{{ t('crm.lead_form.sms_disabled') }}</p>
            </form>
            <template #footer>
                <Button variant="ghost" @click="sendOpen = false">{{ t('common.cancel') }}</Button>
                <Button type="submit" form="send-lead-form-link" :loading="sendForm.processing">{{ t('crm.lead_form.send') }}</Button>
            </template>
        </Modal>
    </AppLayout>
</template>
