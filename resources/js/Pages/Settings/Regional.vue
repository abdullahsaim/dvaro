<script setup>
// Settings → Regional. Timezone drives what staff see, what documents say, and
// when the daily fleet digest is sent (07:00 in YOUR zone, not Sydney's).
import { computed } from 'vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import Button from '@/Components/UI/Button.vue';
import Select from '@/Components/UI/Select.vue';

const props = defineProps({
    settings: { type: Object, required: true },
    timezones: { type: Array, required: true }, // [{ value, label, now }]
    dateFormats: { type: Array, required: true }, // [{ value, example }]
    canManage: { type: Boolean, default: false },
});

const { t } = useI18n();
const page = usePage();
const base = computed(() => `/app/${page.props.tenant.slug}/settings/regional`);

const form = useForm({
    timezone: props.settings.timezone,
    currency: props.settings.currency,
    date_format: props.settings.date_format,
});

const selectedZone = computed(() => props.timezones.find((z) => z.value === form.timezone));

function submit() {
    form.put(base.value, { preserveScroll: true });
}
</script>

<template>
    <AppLayout>
        <Head :title="t('settings.regional.title')" />

        <PageHeader :title="t('settings.regional.title')" :description="t('settings.regional.intro')">
            <template #actions>
                <Button variant="ghost" @click="router.visit(`/app/${page.props.tenant.slug}/settings`)">
                    {{ t('common.back') }}
                </Button>
            </template>
        </PageHeader>

        <form class="max-w-2xl space-y-4" @submit.prevent="submit">
            <div class="rounded-card border border-ink-200 bg-white p-5 shadow-subtle dark:border-ink-800 dark:bg-ink-900">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <Select
                        v-model="form.timezone"
                        :disabled="!canManage"
                        :label="t('settings.regional.timezone')"
                        :error="form.errors.timezone"
                    >
                        <option v-for="zone in timezones" :key="zone.value" :value="zone.value">
                            {{ zone.label }} — {{ zone.now }}
                        </option>
                    </Select>

                    <Select
                        v-model="form.date_format"
                        :disabled="!canManage"
                        :label="t('settings.regional.date_format')"
                        :error="form.errors.date_format"
                    >
                        <option v-for="format in dateFormats" :key="format.value" :value="format.value">
                            {{ format.example }}
                        </option>
                    </Select>

                    <Select
                        v-model="form.currency"
                        disabled
                        :label="t('settings.regional.currency')"
                        :help="t('settings.regional.currency_hint')"
                    >
                        <option value="AUD">AUD — Australian dollar</option>
                    </Select>
                </div>

                <p class="mt-4 rounded-control bg-ink-50 px-3 py-2 text-sm text-ink-600 dark:bg-ink-950 dark:text-ink-300">
                    {{ t('settings.regional.effect', { zone: selectedZone?.label ?? form.timezone, now: selectedZone?.now ?? '' }) }}
                </p>
            </div>

            <div v-if="canManage" class="flex justify-end">
                <Button type="submit" :loading="form.processing">{{ t('common.save') }}</Button>
            </div>
            <p v-else class="text-sm text-ink-500">{{ t('settings.admin_only') }}</p>
        </form>
    </AppLayout>
</template>
