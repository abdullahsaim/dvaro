<script setup>
// Notification settings — provider selection + per-channel toggles.
// tenant_admin only (enforced server-side). Design-system pass.
import { computed } from 'vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import Button from '@/Components/UI/Button.vue';
import Select from '@/Components/UI/Select.vue';
import Input from '@/Components/UI/Input.vue';

const props = defineProps({
    settings: { type: Object, required: true },
    emailProviders: { type: Array, required: true },
    smsProviders: { type: Array, required: true },
});

const { t } = useI18n();
const page = usePage();
const base = computed(() => `/app/${page.props.tenant.slug}/notifications/settings`);

const checkbox = 'h-4 w-4 rounded border-ink-300 accent-ink-900 dark:border-ink-700 dark:accent-ink-100';

const form = useForm({
    email_provider: props.settings.email_provider,
    sms_provider: props.settings.sms_provider,
    notify_email_enabled: props.settings.notify_email_enabled,
    notify_sms_enabled: props.settings.notify_sms_enabled,
    notify_whatsapp_enabled: props.settings.notify_whatsapp_enabled,
    fleet_reminders_enabled: props.settings.fleet_reminders_enabled,
    fleet_reminder_days: props.settings.fleet_reminder_days,
    fleet_reminder_km: props.settings.fleet_reminder_km,
});

function submit() {
    form.put(base.value, { preserveScroll: true });
}
</script>

<template>
    <AppLayout>
        <Head :title="t('notifications.title')" />

        <PageHeader :title="t('notifications.title')" :description="t('notifications.intro')" />

        <form class="max-w-2xl space-y-6" @submit.prevent="submit">
            <!-- Providers -->
            <div class="rounded-card border border-ink-200 bg-white p-4 shadow-subtle dark:border-ink-800 dark:bg-ink-900">
                <h2 class="text-sm font-semibold text-ink-700 dark:text-ink-200">{{ t('notifications.providers') }}</h2>

                <div class="mt-4 space-y-4">
                    <Select v-model="form.email_provider" :label="t('notifications.email_provider')" :error="form.errors.email_provider">
                        <option v-for="p in emailProviders" :key="p" :value="p">{{ t(`notifications.email_providers.${p}`) }}</option>
                    </Select>
                    <Select v-model="form.sms_provider" :label="t('notifications.sms_provider')" :error="form.errors.sms_provider">
                        <option v-for="p in smsProviders" :key="p" :value="p">{{ t(`notifications.sms_providers.${p}`) }}</option>
                    </Select>
                </div>

                <p class="mt-3 text-xs text-ink-400">{{ t('notifications.whatsapp_note') }}</p>
            </div>

            <!-- Channel toggles -->
            <div class="rounded-card border border-ink-200 bg-white p-4 shadow-subtle dark:border-ink-800 dark:bg-ink-900">
                <h2 class="text-sm font-semibold text-ink-700 dark:text-ink-200">{{ t('notifications.channels') }}</h2>

                <label class="mt-4 flex items-center gap-3">
                    <input v-model="form.notify_email_enabled" type="checkbox" :class="checkbox" />
                    <span class="text-sm text-ink-600 dark:text-ink-300">{{ t('notifications.channel_email') }}</span>
                </label>
                <label class="mt-3 flex items-center gap-3">
                    <input v-model="form.notify_sms_enabled" type="checkbox" :class="checkbox" />
                    <span class="text-sm text-ink-600 dark:text-ink-300">{{ t('notifications.channel_sms') }}</span>
                </label>
                <label class="mt-3 flex items-center gap-3">
                    <input v-model="form.notify_whatsapp_enabled" type="checkbox" :class="checkbox" />
                    <span class="text-sm text-ink-600 dark:text-ink-300">{{ t('notifications.channel_whatsapp') }}</span>
                </label>
            </div>

            <!-- Fleet reminders (daily digest to all staff, email) -->
            <div class="rounded-card border border-ink-200 bg-white p-4 shadow-subtle dark:border-ink-800 dark:bg-ink-900">
                <h2 class="text-sm font-semibold text-ink-700 dark:text-ink-200">{{ t('notifications.fleet.title') }}</h2>
                <p class="mt-1 text-sm text-ink-500">{{ t('notifications.fleet.hint') }}</p>

                <label class="mt-4 flex items-center gap-3">
                    <input v-model="form.fleet_reminders_enabled" type="checkbox" :class="checkbox" />
                    <span class="text-sm text-ink-600 dark:text-ink-300">{{ t('notifications.fleet.enabled') }}</span>
                </label>

                <div class="mt-4 grid grid-cols-1 gap-4 transition-opacity duration-150 sm:grid-cols-2" :class="form.fleet_reminders_enabled ? '' : 'pointer-events-none opacity-50'">
                    <Input
                        v-model="form.fleet_reminder_days"
                        type="number"
                        min="1"
                        max="180"
                        :label="t('notifications.fleet.days')"
                        :help="t('notifications.fleet.days_help')"
                        :error="form.errors.fleet_reminder_days"
                    />
                    <Input
                        v-model="form.fleet_reminder_km"
                        type="number"
                        min="100"
                        step="100"
                        :label="t('notifications.fleet.km')"
                        :help="t('notifications.fleet.km_help')"
                        :error="form.errors.fleet_reminder_km"
                    />
                </div>
                <p v-if="!form.notify_email_enabled" class="mt-3 text-xs text-warning-700 dark:text-warning-500">{{ t('notifications.fleet.email_off') }}</p>
            </div>

            <Button type="submit" :loading="form.processing">{{ t('common.save') }}</Button>
        </form>
    </AppLayout>
</template>
