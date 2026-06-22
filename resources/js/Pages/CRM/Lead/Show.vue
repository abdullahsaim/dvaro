<script setup>
// Lead detail. FUNCTIONAL ONLY — design pass later.
// Full data, intake link, convert (disabled unless convertible) + expire.
import { computed, ref } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    lead: { type: Object, required: true },
    link: { type: String, required: true },
    isConvertible: { type: Boolean, required: true },
    isExpired: { type: Boolean, required: true },
});

const { t } = useI18n();
const page = usePage();

const slug = computed(() => page.props.tenant.slug);
const base = computed(() => `/app/${slug.value}/leads`);
const flash = computed(() => page.props.flash?.success);

// Fields rendered in the detail grid (label key → value).
const fields = computed(() => [
    ['email', props.lead.email],
    ['phone', props.lead.phone],
    ['address', props.lead.address],
    ['licence_number', props.lead.licence_number],
    ['emergency_contact_name', props.lead.emergency_contact_name],
    ['emergency_contact_phone', props.lead.emergency_contact_phone],
    ['rental_start_date', props.lead.rental_start_date],
    ['rental_duration', props.lead.rental_duration],
    ['notes', props.lead.notes],
]);

function convert() {
    router.post(`${base.value}/${props.lead.id}/convert`, {}, { preserveScroll: true });
}

function expire() {
    if (!window.confirm(t('crm.confirm_expire'))) return;
    router.post(`${base.value}/${props.lead.id}/expire`, {}, { preserveScroll: true });
}

const copied = ref(false);
async function copyLink() {
    try {
        await navigator.clipboard.writeText(props.link);
        copied.value = true;
        setTimeout(() => (copied.value = false), 2000);
    } catch {
        // Clipboard unavailable — user can select manually.
    }
}
</script>

<template>
    <AppLayout>
        <Head :title="lead.name" />

        <div class="py-10">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <h1 class="text-2xl font-semibold">{{ lead.name }}</h1>
                    <span class="inline-flex items-center rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-medium text-slate-700 dark:bg-slate-800 dark:text-slate-300">
                        {{ t(`crm.statuses.${lead.status}`) }}
                    </span>
                    <span v-if="isExpired" class="inline-flex items-center rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-medium text-amber-800 dark:bg-amber-900/40 dark:text-amber-300">
                        {{ t('crm.expired_badge') }}
                    </span>
                </div>
                <Link :href="base" class="text-sm text-slate-500 hover:underline dark:text-slate-400">
                    {{ t('common.back') }}
                </Link>
            </div>

            <p
                v-if="flash"
                class="mt-4 rounded border border-green-200 bg-green-50 px-3 py-2 text-sm text-green-800 dark:border-green-900 dark:bg-green-900/30 dark:text-green-300"
            >
                {{ flash }}
            </p>

            <!-- Detail grid -->
            <dl class="mt-6 grid max-w-2xl grid-cols-1 gap-x-8 gap-y-3 sm:grid-cols-2">
                <div v-for="[key, value] in fields" :key="key">
                    <dt class="text-sm text-slate-500 dark:text-slate-400">{{ t(`crm.fields.${key}`) }}</dt>
                    <dd class="text-sm">{{ value || t('common.none') }}</dd>
                </div>
                <div>
                    <dt class="text-sm text-slate-500 dark:text-slate-400">{{ t('crm.submitted_at') }}</dt>
                    <dd class="text-sm">{{ lead.submitted_at || t('crm.not_submitted') }}</dd>
                </div>
                <div v-if="lead.converted_at">
                    <dt class="text-sm text-slate-500 dark:text-slate-400">{{ t('crm.converted_at') }}</dt>
                    <dd class="text-sm">{{ lead.converted_at }}</dd>
                </div>
            </dl>

            <!-- Intake link -->
            <div class="mt-8 max-w-2xl">
                <h2 class="text-sm font-medium text-slate-700 dark:text-slate-300">{{ t('crm.intake_link') }}</h2>
                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ t('crm.intake_link_hint') }}</p>
                <div class="mt-2 flex gap-2">
                    <input
                        :value="link"
                        readonly
                        class="w-full rounded border border-slate-300 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900"
                        @focus="$event.target.select()"
                    />
                    <button
                        type="button"
                        class="shrink-0 rounded bg-slate-100 px-3 py-2 text-sm text-slate-700 hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700"
                        @click="copyLink"
                    >
                        {{ copied ? t('crm.copied') : t('crm.copy') }}
                    </button>
                </div>
            </div>

            <!-- Actions -->
            <div class="mt-8 flex flex-wrap items-center gap-3">
                <button
                    type="button"
                    :disabled="!isConvertible"
                    :title="!isConvertible ? t('crm.not_convertible_hint') : ''"
                    class="rounded bg-green-700 px-4 py-2 text-sm text-white hover:bg-green-800 disabled:cursor-not-allowed disabled:opacity-40"
                    @click="convert"
                >
                    {{ t('crm.convert') }}
                </button>

                <Link
                    v-if="lead.converted_customer_id"
                    :href="`/app/${slug}/customers/${lead.converted_customer_id}`"
                    class="text-sm text-indigo-600 hover:underline dark:text-indigo-400"
                >
                    {{ t('crm.view_customer') }}
                </Link>

                <button
                    v-if="!lead.expires_manually && lead.status !== 'converted'"
                    type="button"
                    class="rounded border border-amber-300 px-4 py-2 text-sm text-amber-700 hover:bg-amber-50 dark:border-amber-800 dark:text-amber-400 dark:hover:bg-amber-900/20"
                    @click="expire"
                >
                    {{ t('crm.expire') }}
                </button>
            </div>
        </div>
    </AppLayout>
</template>
