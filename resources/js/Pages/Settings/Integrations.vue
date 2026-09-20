<script setup>
// Settings → Integrations. Pick the AI assistant and messaging providers, and
// see what the platform has configured. A provider without credentials on this
// server is marked clearly: choosing it would silently fall back to "log".
import { computed } from 'vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { CheckCircleIcon, ExclamationTriangleIcon } from '@heroicons/vue/24/outline';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import Button from '@/Components/UI/Button.vue';
import Select from '@/Components/UI/Select.vue';
import StatusBadge from '@/Components/UI/StatusBadge.vue';

const props = defineProps({
    settings: { type: Object, required: true },
    aiProviders: { type: Array, required: true },
    emailProviders: { type: Array, required: true },
    smsProviders: { type: Array, required: true },
    configured: { type: Object, required: true },
    platform: { type: Object, required: true },
    canManage: { type: Boolean, default: false },
});

const { t } = useI18n();
const page = usePage();
const base = computed(() => `/app/${page.props.tenant.slug}/settings/integrations`);

const form = useForm({
    ai_provider: props.settings.ai_provider,
    email_provider: props.settings.email_provider,
    sms_provider: props.settings.sms_provider,
});

const isConfigured = (provider) => provider === 'log' || props.configured[provider] === true;

// Warn when a chosen provider has no credentials on this server.
const warnings = computed(() =>
    [
        { key: 'ai_provider', value: form.ai_provider },
        { key: 'email_provider', value: form.email_provider },
        { key: 'sms_provider', value: form.sms_provider },
    ].filter((f) => !isConfigured(f.value)),
);

function submit() {
    form.put(base.value, { preserveScroll: true });
}

const card = 'rounded-card border border-ink-200 bg-white p-5 shadow-subtle dark:border-ink-800 dark:bg-ink-900';
</script>

<template>
    <AppLayout>
        <Head :title="t('settings.integrations.title')" />

        <PageHeader :title="t('settings.integrations.title')" :description="t('settings.integrations.intro')">
            <template #actions>
                <Button variant="ghost" @click="router.visit(`/app/${page.props.tenant.slug}/settings`)">
                    {{ t('common.back') }}
                </Button>
            </template>
        </PageHeader>

        <div class="grid max-w-4xl grid-cols-1 gap-6 lg:grid-cols-3">
            <form class="space-y-4 lg:col-span-2" @submit.prevent="submit">
                <section :class="card">
                    <h2 class="text-sm font-semibold text-ink-900 dark:text-ink-50">{{ t('settings.integrations.providers') }}</h2>
                    <p class="mt-1 text-sm text-ink-500">{{ t('settings.integrations.providers_hint') }}</p>

                    <div class="mt-4 space-y-4">
                        <Select v-model="form.ai_provider" :disabled="!canManage" :label="t('settings.integrations.ai')" :error="form.errors.ai_provider">
                            <option v-for="p in aiProviders" :key="p" :value="p">
                                {{ t(`settings.integrations.ai_providers.${p}`) }}{{ isConfigured(p) ? '' : ' — ' + t('settings.integrations.not_configured') }}
                            </option>
                        </Select>
                        <Select v-model="form.email_provider" :disabled="!canManage" :label="t('settings.integrations.email')" :error="form.errors.email_provider">
                            <option v-for="p in emailProviders" :key="p" :value="p">
                                {{ t(`notifications.email_providers.${p}`) }}{{ isConfigured(p) ? '' : ' — ' + t('settings.integrations.not_configured') }}
                            </option>
                        </Select>
                        <Select v-model="form.sms_provider" :disabled="!canManage" :label="t('settings.integrations.sms')" :error="form.errors.sms_provider">
                            <option v-for="p in smsProviders" :key="p" :value="p">
                                {{ t(`notifications.sms_providers.${p}`) }}{{ isConfigured(p) ? '' : ' — ' + t('settings.integrations.not_configured') }}
                            </option>
                        </Select>
                    </div>

                    <div
                        v-if="warnings.length"
                        class="mt-4 flex items-start gap-2 rounded-control bg-warning-50 px-3 py-2 text-sm text-warning-700 dark:bg-warning-900/40 dark:text-warning-500"
                    >
                        <ExclamationTriangleIcon class="mt-0.5 h-4 w-4 shrink-0" />
                        <p>{{ t('settings.integrations.fallback_warning') }}</p>
                    </div>
                </section>

                <div v-if="canManage" class="flex justify-end">
                    <Button type="submit" :loading="form.processing">{{ t('common.save') }}</Button>
                </div>
                <p v-else class="text-sm text-ink-500">{{ t('settings.admin_only') }}</p>
            </form>

            <!-- Platform-managed, read-only -->
            <aside :class="card" class="h-fit">
                <h2 class="text-sm font-semibold text-ink-900 dark:text-ink-50">{{ t('settings.integrations.platform') }}</h2>
                <p class="mt-1 text-sm text-ink-500">{{ t('settings.integrations.platform_hint') }}</p>

                <ul class="mt-4 space-y-3 text-sm">
                    <li v-for="key in ['stripe', 'recaptcha']" :key="key" class="flex items-center justify-between gap-2">
                        <span class="text-ink-700 dark:text-ink-200">{{ t(`settings.integrations.${key}`) }}</span>
                        <StatusBadge
                            :variant="platform[key] ? 'success' : 'neutral'"
                            :label="platform[key] ? t('settings.integrations.live') : t('settings.integrations.not_configured')"
                        />
                    </li>
                </ul>

                <p class="mt-4 flex items-start gap-2 text-xs text-ink-400">
                    <CheckCircleIcon class="mt-0.5 h-4 w-4 shrink-0" />
                    {{ t('settings.integrations.platform_note') }}
                </p>
            </aside>
        </div>
    </AppLayout>
</template>
