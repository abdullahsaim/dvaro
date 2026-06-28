<script setup>
// Vehicle detail. FUNCTIONAL ONLY — design pass later.
// Status change uses its own endpoint (ChangeVehicleStatusAction).
import { computed } from 'vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import AppLayout from '@/Layouts/AppLayout.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import { useCurrency } from '@/composables/useCurrency';

const props = defineProps({
    vehicle: { type: Object, required: true },
    statuses: { type: Array, required: true },
    hasQr: { type: Boolean, default: false },
    serviceLogs: { type: Array, default: () => [] },
});

const { t } = useI18n();
const { formatAUD } = useCurrency();
const page = usePage();
const base = computed(() => `/app/${page.props.tenant.slug}/fleet`);
const workshopBase = computed(() => `/app/${page.props.tenant.slug}/workshop`);
const flash = computed(() => page.props.flash?.success);

const statusForm = useForm({ status: props.vehicle.status });

function changeStatus() {
    statusForm.post(`${base.value}/${props.vehicle.id}/status`, { preserveScroll: true });
}

// QR image URL (stream endpoint); cache-busted so a regenerate is reflected.
const qrUrl = computed(() => `${base.value}/${props.vehicle.id}/qr?v=${props.vehicle.qr_code_token ?? ''}`);

function generateQr() {
    router.post(`${base.value}/${props.vehicle.id}/qr`, {}, { preserveScroll: true });
}

const rows = computed(() => [
    { label: t('fleet.fields.registration_number'), value: props.vehicle.registration_number },
    { label: t('fleet.fields.make'), value: props.vehicle.make },
    { label: t('fleet.fields.model'), value: props.vehicle.model },
    { label: t('fleet.fields.year'), value: props.vehicle.year },
    { label: t('fleet.fields.daily_rate'), value: props.vehicle.daily_rate },
    { label: t('fleet.fields.insurance_company'), value: props.vehicle.insurance_company },
    { label: t('fleet.fields.insurance_expiry'), value: props.vehicle.insurance_expiry },
    { label: t('fleet.fields.registration_expiry'), value: props.vehicle.registration_expiry },
    { label: t('fleet.fields.last_service_date'), value: props.vehicle.last_service_date },
    { label: t('fleet.fields.next_service_due'), value: props.vehicle.next_service_due },
    { label: t('fleet.fields.notes'), value: props.vehicle.notes },
]);
</script>

<template>
    <AppLayout>
        <Head :title="t('fleet.vehicle_details')" />

        <div class="py-10">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <h1 class="text-2xl font-semibold">{{ vehicle.make }} {{ vehicle.model }}</h1>
                    <StatusBadge :status="vehicle.status" />
                </div>
                <div class="flex items-center gap-3">
                    <Link :href="`${base}/${vehicle.id}/edit`" class="text-sm text-indigo-600 hover:underline dark:text-indigo-400">
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

            <!-- Status change -->
            <div class="mt-6 flex max-w-xl flex-wrap items-end gap-3 rounded border border-slate-200 p-4 dark:border-slate-800">
                <label class="block">
                    <span class="text-sm text-slate-600 dark:text-slate-300">{{ t('fleet.change_status') }}</span>
                    <select v-model="statusForm.status" class="mt-1 rounded border border-slate-300 px-3 py-2 dark:border-slate-700 dark:bg-slate-900">
                        <option v-for="s in statuses" :key="s" :value="s">{{ t(`fleet.statuses.${s}`) }}</option>
                    </select>
                </label>
                <button
                    type="button"
                    :disabled="statusForm.processing || statusForm.status === vehicle.status"
                    class="rounded bg-slate-700 px-3 py-2 text-sm text-white disabled:opacity-50 dark:bg-slate-300 dark:text-slate-900"
                    @click="changeStatus"
                >
                    {{ t('fleet.update_status') }}
                </button>
            </div>

            <!-- Details -->
            <dl class="mt-6 grid max-w-2xl grid-cols-1 gap-px overflow-hidden rounded border border-slate-200 bg-slate-200 sm:grid-cols-2 dark:border-slate-800 dark:bg-slate-800">
                <div v-for="row in rows" :key="row.label" class="bg-white p-4 dark:bg-slate-950">
                    <dt class="text-sm text-slate-500 dark:text-slate-400">{{ row.label }}</dt>
                    <dd class="mt-1 font-medium">{{ row.value ?? t('common.none') }}</dd>
                </div>
            </dl>

            <!-- QR code -->
            <div class="mt-8 max-w-xl rounded border border-slate-200 p-4 dark:border-slate-800">
                <h2 class="text-sm font-medium uppercase tracking-wide text-slate-500 dark:text-slate-400">
                    {{ t('workshop.qr.title') }}
                </h2>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ t('workshop.qr.hint') }}</p>

                <div class="mt-4 flex items-center gap-6">
                    <img v-if="hasQr" :src="qrUrl" alt="QR" class="h-40 w-40 rounded bg-white p-2" />
                    <p v-else class="text-sm text-slate-500 dark:text-slate-400">{{ t('workshop.qr.none') }}</p>

                    <button
                        type="button"
                        class="rounded bg-slate-700 px-3 py-2 text-sm text-white dark:bg-slate-300 dark:text-slate-900"
                        @click="generateQr"
                    >
                        {{ hasQr ? t('workshop.qr.regenerate') : t('workshop.qr.generate') }}
                    </button>
                </div>
            </div>

            <!-- Service history -->
            <div class="mt-8 max-w-2xl">
                <div class="flex items-center justify-between">
                    <h2 class="text-sm font-medium uppercase tracking-wide text-slate-500 dark:text-slate-400">
                        {{ t('workshop.service_history') }}
                    </h2>
                    <Link
                        :href="`${workshopBase}/vehicle/${vehicle.id}`"
                        class="text-sm text-indigo-600 hover:underline dark:text-indigo-400"
                    >
                        {{ t('common.all') }}
                    </Link>
                </div>

                <p v-if="!serviceLogs.length" class="mt-3 text-sm text-slate-500 dark:text-slate-400">
                    {{ t('workshop.no_history') }}
                </p>
                <ul v-else class="mt-3 space-y-2">
                    <li
                        v-for="log in serviceLogs"
                        :key="log.id"
                        class="flex items-center justify-between rounded border border-slate-200 p-3 text-sm dark:border-slate-800"
                    >
                        <div>
                            <Link :href="`${workshopBase}/${log.id}`" class="font-medium text-indigo-600 hover:underline dark:text-indigo-400">
                                {{ log.title }}
                            </Link>
                            <span class="ml-2 text-slate-400">{{ log.mechanic?.name }}</span>
                        </div>
                        <div class="flex items-center gap-3">
                            <span class="rounded-full bg-slate-100 px-2.5 py-0.5 text-xs dark:bg-slate-800">
                                {{ t(`workshop.statuses.${log.status}`) }}
                            </span>
                            <span class="text-slate-500 dark:text-slate-400">{{ formatAUD(log.total_cost) }}</span>
                        </div>
                    </li>
                </ul>
            </div>
        </div>
    </AppLayout>
</template>
