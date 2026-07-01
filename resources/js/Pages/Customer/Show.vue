<script setup>
// Customer detail — design-system pass. Blacklist / unblacklist use their own
// endpoints (Blacklist/UnblacklistCustomerAction), never the edit form.
import { computed } from 'vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import StatusBadge from '@/Components/UI/StatusBadge.vue';
import Button from '@/Components/UI/Button.vue';
import Textarea from '@/Components/UI/Textarea.vue';
import { useCurrency } from '@/composables/useCurrency';

const props = defineProps({
    customer: { type: Object, required: true },
    outstandingBalance: { type: Number, required: true }, // cents; positive = owes
    hasPortalAccess: { type: Boolean, default: false },
    rentalHistory: { type: Array, default: () => [] },
});

const { t } = useI18n();
const { formatAUD } = useCurrency();
const page = usePage();
const base = computed(() => `/app/${page.props.tenant.slug}/customers`);

const inviteForm = useForm({});

function invitePortal() {
    inviteForm.post(`${base.value}/${props.customer.id}/invite-portal`, { preserveScroll: true });
}

const formattedBalance = computed(() => formatAUD(props.outstandingBalance));

function toDate(value) {
    return value ? String(value).slice(0, 10) : t('common.none');
}

const rows = computed(() => [
    { label: t('customer.fields.name'), value: props.customer.name },
    { label: t('customer.fields.email'), value: props.customer.email },
    { label: t('customer.fields.phone'), value: props.customer.phone },
    { label: t('customer.fields.date_of_birth'), value: toDate(props.customer.date_of_birth) },
    { label: t('customer.fields.licence_number'), value: props.customer.licence_number },
    { label: t('customer.fields.licence_expiry'), value: toDate(props.customer.licence_expiry) },
    { label: t('customer.fields.passport_number'), value: props.customer.passport_number },
    { label: t('customer.fields.address'), value: props.customer.address },
    { label: t('customer.fields.emergency_contact_name'), value: props.customer.emergency_contact_name },
    { label: t('customer.fields.emergency_contact_phone'), value: props.customer.emergency_contact_phone },
    { label: t('customer.fields.risk_notes'), value: props.customer.risk_notes },
]);

const blacklistForm = useForm({ reason: '' });

function blacklist() {
    blacklistForm.post(`${base.value}/${props.customer.id}/blacklist`, {
        preserveScroll: true,
        onSuccess: () => blacklistForm.reset('reason'),
    });
}

const unblacklistForm = useForm({});

function unblacklist() {
    unblacklistForm.post(`${base.value}/${props.customer.id}/unblacklist`, { preserveScroll: true });
}
</script>

<template>
    <AppLayout>
        <Head :title="t('customer.customer_details')" />

        <PageHeader>
            <template #title>
                <span class="flex items-center gap-3">
                    {{ customer.name }}
                    <StatusBadge
                        :variant="customer.is_blacklisted ? 'danger' : 'success'"
                        :label="customer.is_blacklisted ? t('customer.blacklisted_badge') : t('customer.active_badge')"
                    />
                </span>
            </template>
            <template #actions>
                <Button variant="secondary" @click="router.visit(`${base}/${customer.id}/edit`)">{{ t('common.edit') }}</Button>
                <Button variant="ghost" @click="router.visit(base)">{{ t('common.back') }}</Button>
            </template>
        </PageHeader>

        <div class="grid max-w-2xl grid-cols-1 gap-6">
            <!-- Outstanding balance -->
            <div class="flex items-center justify-between rounded-card border border-ink-200 bg-white p-4 shadow-subtle dark:border-ink-800 dark:bg-ink-900">
                <span class="text-sm text-ink-500">{{ t('customer.outstanding_balance') }}</span>
                <span
                    class="text-lg font-semibold tabular-nums"
                    :class="outstandingBalance > 0 ? 'text-warning-700 dark:text-warning-500' : 'text-ink-900 dark:text-ink-50'"
                >
                    {{ formattedBalance }}
                </span>
            </div>

            <!-- Customer portal access -->
            <div class="flex items-center justify-between rounded-card border border-ink-200 bg-white p-4 shadow-subtle dark:border-ink-800 dark:bg-ink-900">
                <div>
                    <p class="text-sm font-medium text-ink-900 dark:text-ink-100">{{ t('customer.portal_access') }}</p>
                    <p class="mt-0.5 text-sm text-ink-500">
                        {{ hasPortalAccess ? t('customer.has_portal_access') : t('customer.portal_access_hint') }}
                    </p>
                </div>
                <StatusBadge v-if="hasPortalAccess" variant="success" :label="t('customer.active_badge')" />
                <Button v-else :loading="inviteForm.processing" @click="invitePortal">{{ t('customer.invite_portal') }}</Button>
            </div>

            <!-- Blacklist control -->
            <div class="rounded-card border border-ink-200 bg-white p-4 shadow-subtle dark:border-ink-800 dark:bg-ink-900">
                <div v-if="customer.is_blacklisted">
                    <p class="text-sm text-ink-600 dark:text-ink-300">
                        <span class="font-medium">{{ t('customer.blacklisted_reason_label') }}:</span>
                        {{ customer.blacklisted_reason }}
                    </p>
                    <Button variant="secondary" class="mt-3" :loading="unblacklistForm.processing" @click="unblacklist">
                        {{ t('customer.unblacklist') }}
                    </Button>
                </div>
                <form v-else class="space-y-3" @submit.prevent="blacklist">
                    <Textarea
                        v-model="blacklistForm.reason"
                        :rows="2"
                        :label="t('customer.blacklist_reason')"
                        :placeholder="t('customer.blacklist_reason_placeholder')"
                        :error="blacklistForm.errors.reason"
                    />
                    <Button type="submit" variant="danger" :loading="blacklistForm.processing">
                        {{ t('customer.blacklist_action') }}
                    </Button>
                </form>
            </div>

            <!-- Details -->
            <dl class="grid grid-cols-1 gap-px overflow-hidden rounded-card border border-ink-200 bg-ink-200 sm:grid-cols-2 dark:border-ink-800 dark:bg-ink-800">
                <div v-for="row in rows" :key="row.label" class="bg-white p-4 dark:bg-ink-900">
                    <dt class="text-sm text-ink-500">{{ row.label }}</dt>
                    <dd class="mt-1 font-medium text-ink-900 dark:text-ink-50">{{ row.value ?? t('common.none') }}</dd>
                </div>
            </dl>

            <!-- Rental history placeholder -->
            <div>
                <h2 class="text-lg font-semibold text-ink-900 dark:text-ink-50">{{ t('customer.rental_history') }}</h2>
                <p class="mt-2 rounded-card border border-dashed border-ink-300 p-4 text-sm text-ink-500 dark:border-ink-700">
                    {{ t('customer.rental_history_placeholder') }}
                </p>
            </div>
        </div>
    </AppLayout>
</template>
