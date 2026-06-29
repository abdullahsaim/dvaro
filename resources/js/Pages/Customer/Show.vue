<script setup>
// Customer detail. FUNCTIONAL ONLY — design pass later.
// Blacklist / unblacklist use their own endpoints (Blacklist/Unblacklist
// CustomerAction), never the edit form.
import { computed } from 'vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    customer: { type: Object, required: true },
    outstandingBalance: { type: Number, required: true }, // cents; positive = owes
    hasPortalAccess: { type: Boolean, default: false },
    rentalHistory: { type: Array, default: () => [] },
});

const { t } = useI18n();
const page = usePage();
const base = computed(() => `/app/${page.props.tenant.slug}/customers`);
const flash = computed(() => page.props.flash?.success);
const flashError = computed(() => page.props.flash?.error);

const inviteForm = useForm({});

function invitePortal() {
    inviteForm.post(`${base.value}/${props.customer.id}/invite-portal`, { preserveScroll: true });
}

// cents → "$1,234.56"
const formattedBalance = computed(() =>
    new Intl.NumberFormat('en-AU', { style: 'currency', currency: 'AUD' }).format(
        (props.outstandingBalance ?? 0) / 100,
    ),
);

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

        <div class="py-10">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <h1 class="text-2xl font-semibold">{{ customer.name }}</h1>
                    <span
                        v-if="customer.is_blacklisted"
                        class="inline-flex items-center rounded-full bg-red-100 px-2.5 py-0.5 text-xs font-medium text-red-800 dark:bg-red-900/40 dark:text-red-300"
                    >
                        {{ t('customer.blacklisted_badge') }}
                    </span>
                    <span
                        v-else
                        class="inline-flex items-center rounded-full bg-green-100 px-2.5 py-0.5 text-xs font-medium text-green-800 dark:bg-green-900/40 dark:text-green-300"
                    >
                        {{ t('customer.active_badge') }}
                    </span>
                </div>
                <div class="flex items-center gap-3">
                    <Link :href="`${base}/${customer.id}/edit`" class="text-sm text-indigo-600 hover:underline dark:text-indigo-400">
                        {{ t('common.edit') }}
                    </Link>
                    <Link :href="base" class="text-sm text-slate-500 hover:underline dark:text-slate-400">
                        {{ t('common.back') }}
                    </Link>
                </div>
            </div>

            <p
                v-if="flash"
                class="mt-4 rounded border border-green-200 bg-green-50 px-3 py-2 text-sm text-green-800 dark:border-green-900 dark:bg-green-900/30 dark:text-green-300"
            >
                {{ flash }}
            </p>
            <p
                v-if="flashError"
                class="mt-4 rounded border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-800 dark:border-red-900 dark:bg-red-900/30 dark:text-red-300"
            >
                {{ flashError }}
            </p>

            <!-- Outstanding balance -->
            <div class="mt-6 flex max-w-2xl items-center justify-between rounded border border-slate-200 p-4 dark:border-slate-800">
                <span class="text-sm text-slate-500 dark:text-slate-400">{{ t('customer.outstanding_balance') }}</span>
                <span
                    class="text-lg font-semibold"
                    :class="outstandingBalance > 0 ? 'text-amber-700 dark:text-amber-400' : 'text-slate-700 dark:text-slate-200'"
                >
                    {{ formattedBalance }}
                </span>
            </div>

            <!-- Customer portal access -->
            <div class="mt-6 flex max-w-2xl items-center justify-between rounded border border-slate-200 p-4 dark:border-slate-800">
                <div>
                    <p class="text-sm font-medium text-slate-700 dark:text-slate-200">{{ t('customer.portal_access') }}</p>
                    <p class="mt-0.5 text-sm text-slate-500 dark:text-slate-400">
                        {{ hasPortalAccess ? t('customer.has_portal_access') : t('customer.portal_access_hint') }}
                    </p>
                </div>
                <span
                    v-if="hasPortalAccess"
                    class="inline-flex items-center rounded-full bg-green-100 px-2.5 py-0.5 text-xs font-medium text-green-800 dark:bg-green-900/40 dark:text-green-300"
                >
                    {{ t('customer.active_badge') }}
                </span>
                <button
                    v-else
                    type="button"
                    :disabled="inviteForm.processing"
                    class="rounded bg-indigo-600 px-3 py-2 text-sm text-white disabled:opacity-50 hover:bg-indigo-700"
                    @click="invitePortal"
                >
                    {{ t('customer.invite_portal') }}
                </button>
            </div>

            <!-- Blacklist control -->
            <div class="mt-6 max-w-2xl rounded border border-slate-200 p-4 dark:border-slate-800">
                <div v-if="customer.is_blacklisted">
                    <p class="text-sm text-slate-600 dark:text-slate-300">
                        <span class="font-medium">{{ t('customer.blacklisted_reason_label') }}:</span>
                        {{ customer.blacklisted_reason }}
                    </p>
                    <button
                        type="button"
                        :disabled="unblacklistForm.processing"
                        class="mt-3 rounded bg-slate-700 px-3 py-2 text-sm text-white disabled:opacity-50 dark:bg-slate-300 dark:text-slate-900"
                        @click="unblacklist"
                    >
                        {{ t('customer.unblacklist') }}
                    </button>
                </div>
                <form v-else class="space-y-3" @submit.prevent="blacklist">
                    <label class="block">
                        <span class="text-sm text-slate-600 dark:text-slate-300">{{ t('customer.blacklist_reason') }}</span>
                        <textarea
                            v-model="blacklistForm.reason"
                            rows="2"
                            :placeholder="t('customer.blacklist_reason_placeholder')"
                            class="mt-1 w-full rounded border border-slate-300 px-3 py-2 dark:border-slate-700 dark:bg-slate-900"
                        />
                        <span v-if="blacklistForm.errors.reason" class="text-xs text-red-600">{{ blacklistForm.errors.reason }}</span>
                    </label>
                    <button
                        type="submit"
                        :disabled="blacklistForm.processing"
                        class="rounded bg-red-600 px-3 py-2 text-sm text-white disabled:opacity-50 hover:bg-red-700"
                    >
                        {{ t('customer.blacklist_action') }}
                    </button>
                </form>
            </div>

            <!-- Details -->
            <dl class="mt-6 grid max-w-2xl grid-cols-1 gap-px overflow-hidden rounded border border-slate-200 bg-slate-200 sm:grid-cols-2 dark:border-slate-800 dark:bg-slate-800">
                <div v-for="row in rows" :key="row.label" class="bg-white p-4 dark:bg-slate-950">
                    <dt class="text-sm text-slate-500 dark:text-slate-400">{{ row.label }}</dt>
                    <dd class="mt-1 font-medium">{{ row.value ?? t('common.none') }}</dd>
                </div>
            </dl>

            <!-- Rental history placeholder -->
            <div class="mt-6 max-w-2xl">
                <h2 class="text-lg font-semibold">{{ t('customer.rental_history') }}</h2>
                <p class="mt-2 rounded border border-dashed border-slate-300 p-4 text-sm text-slate-500 dark:border-slate-700 dark:text-slate-400">
                    {{ t('customer.rental_history_placeholder') }}
                </p>
            </div>
        </div>
    </AppLayout>
</template>
