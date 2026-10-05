<script setup>
// Platform credentials form — Stripe/PayPal/email/AI/SMS secrets, previously
// .env-only. PUT /superadmin/settings/credentials. The server never sends a
// credential's actual value here, only whether each is currently set
// (props.configured) — every field starts blank; typing a value + saving sets
// it, leaving it blank + saving leaves it UNCHANGED (never clears), and
// "Remove" explicitly clears one field back to whatever .env provides.
import { computed, reactive } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import SuperAdminLayout from '@/Layouts/SuperAdminLayout.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import Button from '@/Components/UI/Button.vue';
import Input from '@/Components/UI/Input.vue';
import Select from '@/Components/UI/Select.vue';
import StatusBadge from '@/Components/UI/StatusBadge.vue';

const props = defineProps({
    groups: { type: Object, required: true },
    configured: { type: Object, required: true },
});

const { t } = useI18n();

// Every known key starts blank regardless of configured-state — the server
// never echoes a value back.
const allKeys = computed(() => Object.values(props.groups).flat());
const formFields = Object.fromEntries(allKeys.value.map((k) => [k, '']));
const form = useForm({ ...formFields, clear: [] });

// Per-field "Remove" toggles a key into form.clear and blanks its input so
// the two actions (type a new value vs. remove it) can't be combined by
// mistake for the same field in one submit.
function toggleClear(key) {
    const idx = form.clear.indexOf(key);
    if (idx === -1) {
        form.clear.push(key);
        form[key] = '';
    } else {
        form.clear.splice(idx, 1);
    }
}

function isCleared(key) {
    return form.clear.includes(key);
}

function submit() {
    form.put('/superadmin/settings/credentials', {
        preserveScroll: true,
        onSuccess: () => {
            // Clear every typed value after a successful save — nothing
            // sensitive should linger in the form state longer than needed.
            allKeys.value.forEach((k) => { form[k] = ''; });
            form.clear = [];
        },
    });
}

// Field metadata per key: label, help text, input type, and (for selects)
// options. Declared once here rather than scattered across the template.
const FIELD_META = {
    stripe_key: { label: 'Publishable key', type: 'text', placeholder: 'pk_live_…' },
    stripe_secret: { label: 'Secret key', type: 'password', placeholder: 'sk_live_…' },
    stripe_webhook_secret: { label: 'Webhook signing secret', type: 'password', placeholder: 'whsec_…' },

    paypal_mode: { label: 'Mode', type: 'select', options: [{ value: 'sandbox', label: 'Sandbox' }, { value: 'live', label: 'Live' }] },
    paypal_client_id: { label: 'Client ID', type: 'password' },
    paypal_client_secret: { label: 'Client secret', type: 'password' },
    paypal_webhook_id: { label: 'Webhook ID', type: 'password' },

    mailgun_domain: { label: 'Domain', type: 'text', placeholder: 'mg.yourcompany.com' },
    mailgun_secret: { label: 'API secret', type: 'password' },

    resend_key: { label: 'API key', type: 'password' },

    smtp_host: { label: 'Host', type: 'text', placeholder: 'smtp.yourprovider.com' },
    smtp_port: { label: 'Port', type: 'number', placeholder: '587' },
    smtp_username: { label: 'Username', type: 'text' },
    smtp_password: { label: 'Password', type: 'password' },
    smtp_encryption: { label: 'Encryption', type: 'select', options: [{ value: 'tls', label: 'TLS' }, { value: 'ssl', label: 'SSL' }, { value: 'none', label: 'None' }] },

    groq_key: { label: 'API key', type: 'password' },
    qwen_key: { label: 'API key', type: 'password' },
    deepseek_key: { label: 'API key', type: 'password' },

    clicksend_username: { label: 'Username', type: 'text' },
    clicksend_api_key: { label: 'API key', type: 'password' },
    clicksend_whatsapp_number: { label: 'WhatsApp sender number', type: 'text', placeholder: '+61…' },

    cellcast_api_key: { label: 'API key', type: 'password' },

    recaptcha_site_key: { label: 'Site key', type: 'text' },
    recaptcha_secret_key: { label: 'Secret key', type: 'password' },
};

const GROUP_LABELS = {
    stripe: 'Stripe',
    paypal: 'PayPal',
    mailgun: 'Mailgun',
    resend: 'Resend',
    smtp: 'SMTP',
    groq: 'Groq',
    qwen: 'Qwen',
    deepseek: 'DeepSeek',
    clicksend: 'ClickSend',
    cellcast: 'Cellcast',
    recaptcha: 'Google reCAPTCHA',
};

const GROUP_SECTIONS = [
    { title: 'Payments', groups: ['stripe', 'paypal'] },
    { title: 'Email', groups: ['mailgun', 'resend', 'smtp'] },
    { title: 'AI assistant', groups: ['groq', 'qwen', 'deepseek'] },
    { title: 'SMS & WhatsApp', groups: ['clicksend', 'cellcast'] },
    { title: 'Public forms', groups: ['recaptcha'] },
];
</script>

<template>
    <SuperAdminLayout>
        <Head :title="t('superadmin.credentials.title')" />

        <PageHeader :title="t('superadmin.credentials.title')" :description="t('superadmin.credentials.subtitle')" />

        <form class="max-w-3xl space-y-10" @submit.prevent="submit">
            <section v-for="section in GROUP_SECTIONS" :key="section.title">
                <h2 class="text-xs font-semibold uppercase tracking-wide text-ink-500">{{ section.title }}</h2>

                <div class="mt-3 space-y-6">
                    <div
                        v-for="groupKey in section.groups"
                        :key="groupKey"
                        class="rounded-card border border-ink-200 bg-white p-4 dark:border-ink-800 dark:bg-ink-900"
                    >
                        <h3 class="text-sm font-semibold text-ink-900 dark:text-ink-50">{{ GROUP_LABELS[groupKey] }}</h3>

                        <div class="mt-3 grid gap-4 sm:grid-cols-2">
                            <div v-for="key in groups[groupKey]" :key="key">
                                <div class="mb-1 flex items-center gap-2">
                                    <StatusBadge
                                        :variant="configured[key] ? 'success' : 'neutral'"
                                        :label="configured[key] ? t('superadmin.credentials.configured') : t('superadmin.credentials.not_configured')"
                                    />
                                    <button
                                        v-if="configured[key]"
                                        type="button"
                                        class="text-2xs text-ink-400 underline-offset-2 hover:text-danger-600 hover:underline dark:hover:text-danger-500"
                                        :class="{ 'text-danger-600 dark:text-danger-500': isCleared(key) }"
                                        @click="toggleClear(key)"
                                    >
                                        {{ isCleared(key) ? t('superadmin.credentials.will_remove') : t('superadmin.credentials.remove') }}
                                    </button>
                                </div>

                                <Select
                                    v-if="FIELD_META[key].type === 'select'"
                                    v-model="form[key]"
                                    :label="FIELD_META[key].label"
                                    :error="form.errors[key]"
                                    :disabled="isCleared(key)"
                                >
                                    <option value="">{{ t('superadmin.credentials.leave_unchanged') }}</option>
                                    <option v-for="opt in FIELD_META[key].options" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                                </Select>
                                <Input
                                    v-else
                                    v-model="form[key]"
                                    :type="FIELD_META[key].type"
                                    :label="FIELD_META[key].label"
                                    :placeholder="isCleared(key) ? t('superadmin.credentials.will_remove') : (FIELD_META[key].placeholder ?? t('superadmin.credentials.leave_unchanged'))"
                                    :error="form.errors[key]"
                                    :disabled="isCleared(key)"
                                    autocomplete="off"
                                />
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <Button type="submit" :loading="form.processing">{{ t('common.save') }}</Button>
        </form>
    </SuperAdminLayout>
</template>
