<script setup>
// Notification settings — provider selection + per-channel toggles.
// tenant_admin only (enforced server-side). FUNCTIONAL ONLY — design pass later.
import { computed } from 'vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    settings: { type: Object, required: true },
    emailProviders: { type: Array, required: true },
    smsProviders: { type: Array, required: true },
});

const { t } = useI18n();
const page = usePage();
const base = computed(() => `/app/${page.props.tenant.slug}/notifications/settings`);
const flash = computed(() => page.props.flash ?? {});

const form = useForm({
    email_provider: props.settings.email_provider,
    sms_provider: props.settings.sms_provider,
    notify_email_enabled: props.settings.notify_email_enabled,
    notify_sms_enabled: props.settings.notify_sms_enabled,
    notify_whatsapp_enabled: props.settings.notify_whatsapp_enabled,
});

function submit() {
    form.put(base.value, { preserveScroll: true });
}
</script>

<template>
    <AppLayout>
        <Head :title="t('notifications.title')" />

        <div class="py-10">
            <h1 class="text-2xl font-semibold">{{ t('notifications.title') }}</h1>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ t('notifications.intro') }}</p>

            <p
                v-if="flash.success"
                class="mt-4 max-w-2xl rounded border border-green-200 bg-green-50 px-3 py-2 text-sm text-green-700 dark:border-green-900 dark:bg-green-950 dark:text-green-300"
            >
                {{ flash.success }}
            </p>

            <form class="mt-6 max-w-2xl space-y-6" @submit.prevent="submit">
                <!-- Providers -->
                <div class="rounded border border-slate-200 p-4 dark:border-slate-800">
                    <h2 class="text-sm font-semibold text-slate-700 dark:text-slate-200">{{ t('notifications.providers') }}</h2>

                    <label class="mt-4 block">
                        <span class="text-sm text-slate-600 dark:text-slate-300">{{ t('notifications.email_provider') }}</span>
                        <select v-model="form.email_provider" class="mt-1 w-full rounded border border-slate-300 px-3 py-2 dark:border-slate-700 dark:bg-slate-900">
                            <option v-for="p in emailProviders" :key="p" :value="p">{{ t(`notifications.email_providers.${p}`) }}</option>
                        </select>
                        <span v-if="form.errors.email_provider" class="text-xs text-red-600">{{ form.errors.email_provider }}</span>
                    </label>

                    <label class="mt-4 block">
                        <span class="text-sm text-slate-600 dark:text-slate-300">{{ t('notifications.sms_provider') }}</span>
                        <select v-model="form.sms_provider" class="mt-1 w-full rounded border border-slate-300 px-3 py-2 dark:border-slate-700 dark:bg-slate-900">
                            <option v-for="p in smsProviders" :key="p" :value="p">{{ t(`notifications.sms_providers.${p}`) }}</option>
                        </select>
                        <span v-if="form.errors.sms_provider" class="text-xs text-red-600">{{ form.errors.sms_provider }}</span>
                    </label>

                    <p class="mt-3 text-xs text-slate-400">{{ t('notifications.whatsapp_note') }}</p>
                </div>

                <!-- Channel toggles -->
                <div class="rounded border border-slate-200 p-4 dark:border-slate-800">
                    <h2 class="text-sm font-semibold text-slate-700 dark:text-slate-200">{{ t('notifications.channels') }}</h2>

                    <label class="mt-4 flex items-center gap-3">
                        <input v-model="form.notify_email_enabled" type="checkbox" class="rounded border-slate-300" />
                        <span class="text-sm text-slate-600 dark:text-slate-300">{{ t('notifications.channel_email') }}</span>
                    </label>
                    <label class="mt-3 flex items-center gap-3">
                        <input v-model="form.notify_sms_enabled" type="checkbox" class="rounded border-slate-300" />
                        <span class="text-sm text-slate-600 dark:text-slate-300">{{ t('notifications.channel_sms') }}</span>
                    </label>
                    <label class="mt-3 flex items-center gap-3">
                        <input v-model="form.notify_whatsapp_enabled" type="checkbox" class="rounded border-slate-300" />
                        <span class="text-sm text-slate-600 dark:text-slate-300">{{ t('notifications.channel_whatsapp') }}</span>
                    </label>
                </div>

                <button
                    type="submit"
                    :disabled="form.processing"
                    class="rounded bg-slate-800 px-4 py-2 text-white disabled:opacity-50 dark:bg-slate-200 dark:text-slate-900"
                >
                    {{ t('common.save') }}
                </button>
            </form>
        </div>
    </AppLayout>
</template>
