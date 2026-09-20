<script setup>
// Settings → Notifications. Master channel switches, the fleet digest lead
// times, and the per-trigger matrix: one row per thing DVARO sends, one column
// per channel. Providers live in Settings → Integrations.
// tenant_admin only (enforced server-side).
import { computed, reactive } from 'vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import Button from '@/Components/UI/Button.vue';
import Input from '@/Components/UI/Input.vue';

const props = defineProps({
    settings: { type: Object, required: true },
    matrix: { type: Array, required: true }, // [{ key, label, description, audience, locked, channels }]
    channels: { type: Array, required: true }, // ['email', 'sms', 'whatsapp']
});

const { t } = useI18n();
const page = usePage();
const base = computed(() => `/app/${page.props.tenant.slug}/notifications/settings`);

const checkbox = 'h-4 w-4 rounded border-ink-300 accent-ink-900 disabled:cursor-not-allowed disabled:opacity-40 dark:border-ink-700 dark:accent-ink-100';

// The matrix as a flat { trigger: { channel: bool } } the form can post back.
const rows = reactive(
    Object.fromEntries(
        props.matrix.map((row) => [
            row.key,
            Object.fromEntries(Object.entries(row.channels).map(([channel, state]) => [channel, state.enabled])),
        ]),
    ),
);

const form = useForm({
    notify_email_enabled: props.settings.notify_email_enabled,
    notify_sms_enabled: props.settings.notify_sms_enabled,
    notify_whatsapp_enabled: props.settings.notify_whatsapp_enabled,
    fleet_reminders_enabled: props.settings.fleet_reminders_enabled,
    fleet_reminder_days: props.settings.fleet_reminder_days,
    fleet_reminder_km: props.settings.fleet_reminder_km,
    matrix: rows,
});

// A master switch that is off makes its whole column inert — shown greyed with
// a note, never silently ignored.
const channelOn = computed(() => ({
    email: form.notify_email_enabled,
    sms: form.notify_sms_enabled,
    whatsapp: form.notify_whatsapp_enabled,
}));

const customerRows = computed(() => props.matrix.filter((row) => row.audience === 'customer'));
const staffRows = computed(() => props.matrix.filter((row) => row.audience === 'staff'));

function supports(row, channel) {
    return Object.prototype.hasOwnProperty.call(row.channels, channel);
}

function submit() {
    form.put(base.value, { preserveScroll: true });
}
</script>

<template>
    <AppLayout>
        <Head :title="t('notifications.title')" />

        <PageHeader :title="t('notifications.title')" :description="t('notifications.intro')">
            <template #actions>
                <Button variant="ghost" @click="router.visit(`/app/${page.props.tenant.slug}/settings`)">
                    {{ t('common.back') }}
                </Button>
            </template>
        </PageHeader>

        <form class="max-w-3xl space-y-4" @submit.prevent="submit">
            <!-- Master channel switches -->
            <div class="rounded-card border border-ink-200 bg-white p-5 shadow-subtle dark:border-ink-800 dark:bg-ink-900">
                <h2 class="text-sm font-semibold text-ink-700 dark:text-ink-200">{{ t('notifications.channels') }}</h2>
                <p class="mt-1 text-sm text-ink-500">{{ t('notifications.channels_hint') }}</p>

                <div class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-3">
                    <label class="flex items-center gap-3">
                        <input v-model="form.notify_email_enabled" type="checkbox" :class="checkbox" />
                        <span class="text-sm text-ink-600 dark:text-ink-300">{{ t('notifications.channel_email') }}</span>
                    </label>
                    <label class="flex items-center gap-3">
                        <input v-model="form.notify_sms_enabled" type="checkbox" :class="checkbox" />
                        <span class="text-sm text-ink-600 dark:text-ink-300">{{ t('notifications.channel_sms') }}</span>
                    </label>
                    <label class="flex items-center gap-3">
                        <input v-model="form.notify_whatsapp_enabled" type="checkbox" :class="checkbox" />
                        <span class="text-sm text-ink-600 dark:text-ink-300">{{ t('notifications.channel_whatsapp') }}</span>
                    </label>
                </div>

                <p class="mt-3 text-xs text-ink-400">{{ t('notifications.whatsapp_note') }}</p>
            </div>

            <!-- The matrix -->
            <div class="overflow-hidden rounded-card border border-ink-200 bg-white shadow-subtle dark:border-ink-800 dark:bg-ink-900">
                <div class="border-b border-ink-200 p-5 dark:border-ink-800">
                    <h2 class="text-sm font-semibold text-ink-700 dark:text-ink-200">{{ t('notifications.matrix.title') }}</h2>
                    <p class="mt-1 text-sm text-ink-500">{{ t('notifications.matrix.hint') }}</p>
                </div>

                <table class="w-full text-left text-sm">
                    <thead class="bg-ink-50 text-xs uppercase tracking-wide text-ink-500 dark:bg-ink-950/40">
                        <tr>
                            <th class="px-5 py-2 font-medium">{{ t('notifications.matrix.trigger') }}</th>
                            <th v-for="channel in channels" :key="channel" class="w-24 px-3 py-2 text-center font-medium">
                                {{ t(`notifications.channel_${channel}`) }}
                                <span v-if="!channelOn[channel]" class="block text-[10px] normal-case text-warning-600">
                                    {{ t('notifications.matrix.channel_off') }}
                                </span>
                            </th>
                        </tr>
                    </thead>

                    <tbody class="divide-y divide-ink-100 dark:divide-ink-800">
                        <template v-for="group in [{ label: t('notifications.matrix.to_customers'), rows: customerRows }, { label: t('notifications.matrix.to_team'), rows: staffRows }]" :key="group.label">
                            <tr class="bg-ink-50/60 dark:bg-ink-950/20">
                                <td :colspan="channels.length + 1" class="px-5 py-2 text-xs font-semibold uppercase tracking-wide text-ink-500">
                                    {{ group.label }}
                                </td>
                            </tr>
                            <tr v-for="row in group.rows" :key="row.key" class="transition-colors hover:bg-ink-50/60 dark:hover:bg-ink-950/20">
                                <td class="px-5 py-3">
                                    <div class="font-medium text-ink-800 dark:text-ink-100">{{ row.label }}</div>
                                    <div class="text-xs text-ink-500">{{ row.description }}</div>
                                    <div v-if="row.locked" class="mt-1 text-xs text-ink-400">{{ t('notifications.matrix.always_on') }}</div>
                                </td>
                                <td v-for="channel in channels" :key="channel" class="px-3 py-3 text-center">
                                    <input
                                        v-if="supports(row, channel)"
                                        v-model="rows[row.key][channel]"
                                        type="checkbox"
                                        :class="checkbox"
                                        :disabled="row.locked || !channelOn[channel]"
                                        :aria-label="`${row.label} — ${channel}`"
                                    />
                                    <span v-else class="text-ink-300 dark:text-ink-700" :title="t('notifications.matrix.not_supported')">—</span>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>

            <!-- Fleet reminders (daily digest to all staff, email) -->
            <div class="rounded-card border border-ink-200 bg-white p-5 shadow-subtle dark:border-ink-800 dark:bg-ink-900">
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
