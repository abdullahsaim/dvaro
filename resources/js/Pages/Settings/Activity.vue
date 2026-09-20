<script setup>
// Settings → Activity: the append-only audit trail (admin only). Read-only by
// design — nothing here can be edited or deleted, in the app or the database.
import { Head, router, usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { computed } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import DataTable from '@/Components/UI/DataTable.vue';
import Button from '@/Components/UI/Button.vue';
import { useTenantFormat } from '@/composables/useTenantFormat';

const props = defineProps({
    entries: { type: Object, required: true }, // paginator
});

const { t } = useI18n();
const page = usePage();

// Times in the COMPANY's timezone and date format (Settings → Regional), not
// whatever this laptop happens to be set to.
const { dateTime } = useTenantFormat();
const when = (v) => (v ? dateTime(v) : '');

// "grace_days: 7 → 14" — only the keys that actually changed are stored.
function changes(entry) {
    const keys = Object.keys(entry.new_values ?? {});
    if (!keys.length) return null;

    return keys
        .map((key) => `${key}: ${format(entry.old_values?.[key])} → ${format(entry.new_values[key])}`)
        .join(', ');
}

function format(value) {
    if (value === null || value === undefined || value === '') return '—';
    if (typeof value === 'boolean') return value ? t('common.yes') : t('common.no');
    if (Array.isArray(value)) return value.length ? value.join(', ') : '—';
    if (typeof value === 'object') return JSON.stringify(value);
    return String(value);
}

const back = computed(() => `/app/${page.props.tenant.slug}/settings`);
</script>

<template>
    <AppLayout>
        <Head :title="t('settings.activity.title')" />

        <PageHeader :title="t('settings.activity.title')" :description="t('settings.activity.intro')">
            <template #actions>
                <Button variant="ghost" @click="router.visit(back)">{{ t('common.back') }}</Button>
            </template>
        </PageHeader>

        <DataTable :columns="4" :empty="entries.data.length === 0" :pagination="entries">
            <template #head>
                <th class="px-4 py-3">{{ t('settings.activity.what') }}</th>
                <th class="px-4 py-3">{{ t('settings.activity.changes') }}</th>
                <th class="px-4 py-3">{{ t('settings.activity.who') }}</th>
                <th class="px-4 py-3 text-right">{{ t('settings.activity.when') }}</th>
            </template>

            <tr v-for="entry in entries.data" :key="entry.id" class="align-top text-ink-700 dark:text-ink-200">
                <td class="px-4 py-3">
                    <p class="font-medium text-ink-900 dark:text-ink-50">
                        {{ t(`settings.actions.${entry.action}`, { subject: entry.subject_label ?? '' }) }}
                    </p>
                    <p v-if="entry.subject_label" class="text-xs text-ink-500">{{ entry.subject_label }}</p>
                </td>
                <td class="px-4 py-3 text-sm">
                    <span v-if="changes(entry)" class="break-words font-mono text-xs text-ink-600 dark:text-ink-300">{{ changes(entry) }}</span>
                    <span v-else class="text-ink-400">—</span>
                </td>
                <td class="px-4 py-3 text-sm">
                    {{ entry.actor_label }}
                    <span v-if="entry.ip" class="block text-xs text-ink-400">{{ entry.ip }}</span>
                </td>
                <td class="whitespace-nowrap px-4 py-3 text-right text-sm tabular-nums">{{ when(entry.created_at) }}</td>
            </tr>

            <template #empty>
                <div class="px-4 py-6 text-center text-sm text-ink-400">{{ t('settings.activity.empty') }}</div>
            </template>
        </DataTable>
    </AppLayout>
</template>
