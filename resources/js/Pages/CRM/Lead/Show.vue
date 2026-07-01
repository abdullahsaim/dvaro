<script setup>
// Lead detail — design-system pass.
// Full data, intake link, convert (disabled unless convertible) + expire.
import { computed, ref } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import StatusBadge from '@/Components/UI/StatusBadge.vue';
import Button from '@/Components/UI/Button.vue';
import Input from '@/Components/UI/Input.vue';

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

const statusVariants = {
    new: 'info',
    contacted: 'neutral',
    converted: 'success',
    expired: 'neutral',
    rejected: 'danger',
};

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

        <PageHeader>
            <template #title>
                <span class="flex flex-wrap items-center gap-3">
                    {{ lead.name }}
                    <StatusBadge :variant="statusVariants[lead.status]" :label="t(`crm.statuses.${lead.status}`)" />
                    <StatusBadge v-if="isExpired" variant="warning" :label="t('crm.expired_badge')" />
                </span>
            </template>
            <template #actions>
                <Button variant="ghost" @click="router.visit(base)">{{ t('common.back') }}</Button>
            </template>
        </PageHeader>

        <div class="max-w-2xl">
            <!-- Detail grid -->
            <dl class="grid grid-cols-1 gap-x-8 gap-y-3 sm:grid-cols-2">
                <div v-for="[key, value] in fields" :key="key">
                    <dt class="text-sm text-ink-500">{{ t(`crm.fields.${key}`) }}</dt>
                    <dd class="text-sm text-ink-900 dark:text-ink-100">{{ value || t('common.none') }}</dd>
                </div>
                <div>
                    <dt class="text-sm text-ink-500">{{ t('crm.submitted_at') }}</dt>
                    <dd class="text-sm text-ink-900 dark:text-ink-100">{{ lead.submitted_at || t('crm.not_submitted') }}</dd>
                </div>
                <div v-if="lead.converted_at">
                    <dt class="text-sm text-ink-500">{{ t('crm.converted_at') }}</dt>
                    <dd class="text-sm text-ink-900 dark:text-ink-100">{{ lead.converted_at }}</dd>
                </div>
            </dl>

            <!-- Intake link -->
            <div class="mt-8">
                <h2 class="text-sm font-medium text-ink-700 dark:text-ink-300">{{ t('crm.intake_link') }}</h2>
                <p class="mt-1 text-xs text-ink-500">{{ t('crm.intake_link_hint') }}</p>
                <div class="mt-2 flex items-end gap-2">
                    <Input :model-value="link" readonly class="w-full" />
                    <Button variant="secondary" class="shrink-0" @click="copyLink">
                        {{ copied ? t('crm.copied') : t('crm.copy') }}
                    </Button>
                </div>
            </div>

            <!-- Actions -->
            <div class="mt-8 flex flex-wrap items-center gap-3">
                <Button
                    variant="primary"
                    :disabled="!isConvertible"
                    :title="!isConvertible ? t('crm.not_convertible_hint') : ''"
                    @click="convert"
                >
                    {{ t('crm.convert') }}
                </Button>

                <Link
                    v-if="lead.converted_customer_id"
                    :href="`/app/${slug}/customers/${lead.converted_customer_id}`"
                    class="text-sm font-medium text-ink-900 hover:underline dark:text-ink-100"
                >
                    {{ t('crm.view_customer') }}
                </Link>

                <Button
                    v-if="!lead.expires_manually && lead.status !== 'converted'"
                    variant="secondary"
                    @click="expire"
                >
                    {{ t('crm.expire') }}
                </Button>
            </div>
        </div>
    </AppLayout>
</template>
