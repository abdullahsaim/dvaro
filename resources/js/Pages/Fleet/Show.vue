<script setup>
// Vehicle detail — design-system pass. Status change uses its own endpoint
// (ChangeVehicleStatusAction), separate from any edit form.
import { computed } from 'vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import StatusBadge from '@/Components/UI/StatusBadge.vue';
import Button from '@/Components/UI/Button.vue';
import Select from '@/Components/UI/Select.vue';
import Input from '@/Components/UI/Input.vue';
import { useCurrency } from '@/composables/useCurrency';

const props = defineProps({
    vehicle: { type: Object, required: true },
    statuses: { type: Array, required: true },
    hasQr: { type: Boolean, default: false },
    serviceLogs: { type: Array, default: () => [] },
    odometerReadings: { type: Array, default: () => [] }, // newest first, append-only
    serviceState: { type: String, default: null }, // overdue | due_soon | ok (date or km, whichever first)
    vehicleExpenses: { type: Array, default: () => [] }, // latest 5 active
    vehicleExpensesFy: { type: Number, default: 0 }, // cents, this FY
});

const { t } = useI18n();
const { formatAUD } = useCurrency();
const page = usePage();
const base = computed(() => `/app/${page.props.tenant.slug}/fleet`);
const workshopBase = computed(() => `/app/${page.props.tenant.slug}/workshop`);

const statusVariants = {
    available: 'success',
    rented: 'info',
    maintenance: 'warning',
    suspended: 'neutral',
    accident: 'danger',
    reserved: 'neutral',
};

const statusForm = useForm({ status: props.vehicle.status });

function changeStatus() {
    statusForm.post(`${base.value}/${props.vehicle.id}/status`, { preserveScroll: true });
}

// QR image URL (stream endpoint); cache-busted so a regenerate is reflected.
const qrUrl = computed(() => `${base.value}/${props.vehicle.id}/qr?v=${props.vehicle.qr_code_token ?? ''}`);

function generateQr() {
    router.post(`${base.value}/${props.vehicle.id}/qr`, {}, { preserveScroll: true });
}

const numberFormat = new Intl.NumberFormat('en-AU');
const dateFormat = new Intl.DateTimeFormat('en-AU', { day: '2-digit', month: 'short', year: 'numeric' });

function km(value) {
    return value === null || value === undefined ? null : `${numberFormat.format(value)} km`;
}

function formatDate(value) {
    if (!value) return null;
    const [y, m, d] = String(value).slice(0, 10).split('-').map(Number);
    return dateFormat.format(new Date(y, m - 1, d));
}

// Service schedule summary — "whichever comes first".
const kmToGo = computed(() => {
    const { next_service_km: next, current_odometer: now } = props.vehicle;
    return next === null || now === null ? null : next - now;
});

const expiryVariants = { overdue: 'danger', due_soon: 'warning' };

const intervalLabel = computed(() => {
    const parts = [];
    if (props.vehicle.service_interval_months) parts.push(t('fleet.schedule.every_months', { n: props.vehicle.service_interval_months }));
    if (props.vehicle.service_interval_km) parts.push(t('fleet.schedule.every_km', { km: numberFormat.format(props.vehicle.service_interval_km) }));
    return parts.length ? parts.join(` ${t('fleet.schedule.or')} `) : null;
});

// Manual odometer entry → RecordOdometerReadingAction (never backwards).
const odometerForm = useForm({ reading: '' });

function recordOdometer() {
    odometerForm.post(`${base.value}/${props.vehicle.id}/odometer`, {
        preserveScroll: true,
        onSuccess: () => odometerForm.reset('reading'),
    });
}

const rows = computed(() => [
    { label: t('fleet.fields.registration_number'), value: props.vehicle.registration_number },
    { label: t('fleet.fields.make'), value: props.vehicle.make },
    { label: t('fleet.fields.model'), value: props.vehicle.model },
    { label: t('fleet.fields.year'), value: props.vehicle.year },
    { label: t('fleet.fields.daily_rate'), value: formatAUD(props.vehicle.daily_rate) },
    { label: t('fleet.fields.insurance_company'), value: props.vehicle.insurance_company },
    { label: t('fleet.fields.insurance_expiry'), value: formatDate(props.vehicle.insurance_expiry) },
    { label: t('fleet.fields.registration_expiry'), value: formatDate(props.vehicle.registration_expiry) },
    { label: t('fleet.fields.notes'), value: props.vehicle.notes },
]);
</script>

<template>
    <AppLayout>
        <Head :title="t('fleet.vehicle_details')" />

        <PageHeader>
            <template #title>
                <span class="flex items-center gap-3">
                    {{ vehicle.make }} {{ vehicle.model }}
                    <StatusBadge :variant="statusVariants[vehicle.status]" :label="t(`fleet.statuses.${vehicle.status}`)" />
                </span>
            </template>
            <template #actions>
                <Button variant="secondary" @click="router.visit(`${base}/${vehicle.id}/edit`)">{{ t('common.edit') }}</Button>
                <Button variant="ghost" @click="router.visit(base)">{{ t('common.back') }}</Button>
            </template>
        </PageHeader>

        <!-- Status change -->
        <div class="mb-6 flex max-w-xl flex-wrap items-end gap-3 rounded-card border border-ink-200 bg-white p-4 shadow-subtle dark:border-ink-800 dark:bg-ink-900">
            <Select v-model="statusForm.status" :label="t('fleet.change_status')" class="min-w-[12rem]">
                <option v-for="s in statuses" :key="s" :value="s">{{ t(`fleet.statuses.${s}`) }}</option>
            </Select>
            <Button
                :loading="statusForm.processing"
                :disabled="statusForm.status === vehicle.status"
                @click="changeStatus"
            >
                {{ t('fleet.update_status') }}
            </Button>
        </div>

        <!-- Details -->
        <dl class="grid max-w-2xl grid-cols-1 gap-px overflow-hidden rounded-card border border-ink-200 bg-ink-200 sm:grid-cols-2 dark:border-ink-800 dark:bg-ink-800">
            <div v-for="row in rows" :key="row.label" class="bg-white p-4 dark:bg-ink-900">
                <dt class="text-sm text-ink-500">{{ row.label }}</dt>
                <dd class="mt-1 font-medium text-ink-900 dark:text-ink-50">{{ row.value ?? t('common.none') }}</dd>
            </div>
        </dl>

        <!-- Odometer + service schedule (months or km, whichever first) -->
        <div class="mt-8 max-w-2xl rounded-card border border-ink-200 bg-white shadow-subtle dark:border-ink-800 dark:bg-ink-900">
            <div class="flex flex-wrap items-center justify-between gap-2 border-b border-ink-200 p-4 dark:border-ink-800">
                <h2 class="text-xs font-medium uppercase tracking-wide text-ink-500">{{ t('fleet.schedule.title') }}</h2>
                <StatusBadge v-if="expiryVariants[serviceState]" :variant="expiryVariants[serviceState]" :label="t(`fleet.expiry.${serviceState}`)" />
            </div>

            <dl class="grid grid-cols-2 gap-px bg-ink-200 sm:grid-cols-4 dark:bg-ink-800">
                <div class="bg-white p-4 dark:bg-ink-900">
                    <dt class="text-xs text-ink-500">{{ t('fleet.fields.current_odometer') }}</dt>
                    <dd class="mt-1 font-semibold tabular-nums text-ink-900 dark:text-ink-50">{{ km(vehicle.current_odometer) ?? t('common.none') }}</dd>
                </div>
                <div class="bg-white p-4 dark:bg-ink-900">
                    <dt class="text-xs text-ink-500">{{ t('fleet.schedule.interval') }}</dt>
                    <dd class="mt-1 text-sm font-medium text-ink-900 dark:text-ink-50">{{ intervalLabel ?? t('common.none') }}</dd>
                </div>
                <div class="bg-white p-4 dark:bg-ink-900">
                    <dt class="text-xs text-ink-500">{{ t('fleet.schedule.last_service') }}</dt>
                    <dd class="mt-1 text-sm font-medium text-ink-900 dark:text-ink-50">
                        {{ formatDate(vehicle.last_service_date) ?? t('common.none') }}
                        <span v-if="vehicle.last_service_odometer !== null" class="block text-xs font-normal tabular-nums text-ink-500">{{ km(vehicle.last_service_odometer) }}</span>
                    </dd>
                </div>
                <div class="bg-white p-4 dark:bg-ink-900">
                    <dt class="text-xs text-ink-500">{{ t('fleet.schedule.next_service') }}</dt>
                    <dd class="mt-1 text-sm font-medium text-ink-900 dark:text-ink-50">
                        {{ formatDate(vehicle.next_service_due) ?? (vehicle.next_service_km === null ? t('common.none') : '') }}
                        <span
                            v-if="vehicle.next_service_km !== null"
                            class="block text-xs font-normal tabular-nums"
                            :class="kmToGo !== null && kmToGo <= 0 ? 'text-danger-600 dark:text-danger-500' : 'text-ink-500'"
                        >
                            {{ km(vehicle.next_service_km) }}
                            <template v-if="kmToGo !== null">
                                · {{ kmToGo > 0
                                    ? t('fleet.schedule.km_to_go', { km: numberFormat.format(kmToGo) })
                                    : t('fleet.schedule.km_over', { km: numberFormat.format(-kmToGo) }) }}
                            </template>
                        </span>
                    </dd>
                </div>
            </dl>

            <form class="flex flex-wrap items-end gap-3 border-t border-ink-200 p-4 dark:border-ink-800" @submit.prevent="recordOdometer">
                <Input
                    v-model="odometerForm.reading"
                    type="number"
                    min="0"
                    inputmode="numeric"
                    class="min-w-[12rem] flex-1"
                    :label="t('fleet.odometer.record_label')"
                    :placeholder="vehicle.current_odometer !== null ? String(vehicle.current_odometer) : ''"
                    :error="odometerForm.errors.reading"
                />
                <Button type="submit" :loading="odometerForm.processing" :disabled="odometerForm.reading === ''">
                    {{ t('fleet.odometer.record') }}
                </Button>
            </form>

            <div class="border-t border-ink-200 p-4 dark:border-ink-800">
                <h3 class="text-xs font-medium uppercase tracking-wide text-ink-500">{{ t('fleet.odometer.history') }}</h3>
                <p v-if="!odometerReadings.length" class="mt-2 text-sm text-ink-500">{{ t('fleet.odometer.none') }}</p>
                <ul v-else class="mt-2 divide-y divide-ink-100 text-sm dark:divide-ink-800">
                    <li v-for="r in odometerReadings" :key="r.id" class="flex items-center justify-between py-2">
                        <span class="font-medium tabular-nums text-ink-900 dark:text-ink-50">{{ km(r.reading) }}</span>
                        <span class="flex items-center gap-3 text-ink-500">
                            <StatusBadge variant="neutral" :label="t(`fleet.odometer.sources.${r.source}`)" />
                            <span class="tabular-nums">{{ formatDate(r.recorded_at) }}</span>
                        </span>
                    </li>
                </ul>
            </div>
        </div>

        <!-- Vehicle expenses (feeds profit per vehicle) -->
        <div class="mt-8 max-w-2xl">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <h2 class="text-xs font-medium uppercase tracking-wide text-ink-500">{{ t('expenses.vehicle_section') }}</h2>
                <Link
                    :href="`/app/${page.props.tenant.slug}/expenses?vehicle=${vehicle.id}`"
                    class="text-sm font-medium text-ink-900 hover:underline dark:text-ink-100"
                >
                    {{ t('common.all') }}
                </Link>
            </div>
            <p class="mt-1 text-sm text-ink-500">{{ t('expenses.vehicle_fy_total', { total: formatAUD(vehicleExpensesFy) }) }}</p>
            <p v-if="!vehicleExpenses.length" class="mt-3 text-sm text-ink-500">{{ t('expenses.vehicle_none') }}</p>
            <ul v-else class="mt-3 divide-y divide-ink-100 rounded-card border border-ink-200 bg-white text-sm shadow-subtle dark:divide-ink-800 dark:border-ink-800 dark:bg-ink-900">
                <li v-for="e in vehicleExpenses" :key="e.id" class="flex items-center justify-between gap-3 px-3 py-2.5">
                    <div class="min-w-0">
                        <p class="truncate font-medium text-ink-900 dark:text-ink-50">{{ e.description }}</p>
                        <p class="text-xs text-ink-500">{{ formatDate(e.expense_date) }} · {{ e.category?.name }}</p>
                    </div>
                    <span class="shrink-0 tabular-nums text-ink-900 dark:text-ink-50">{{ formatAUD(e.amount_total) }}</span>
                </li>
            </ul>
        </div>

        <!-- QR code -->
        <div class="mt-8 max-w-xl rounded-card border border-ink-200 bg-white p-4 shadow-subtle dark:border-ink-800 dark:bg-ink-900">
            <h2 class="text-xs font-medium uppercase tracking-wide text-ink-500">{{ t('workshop.qr.title') }}</h2>
            <p class="mt-1 text-sm text-ink-500">{{ t('workshop.qr.hint') }}</p>

            <div class="mt-4 flex items-center gap-6">
                <img v-if="hasQr" :src="qrUrl" alt="QR" class="h-40 w-40 rounded-control bg-white p-2 ring-1 ring-ink-200 dark:ring-ink-700" />
                <p v-else class="text-sm text-ink-500">{{ t('workshop.qr.none') }}</p>

                <Button variant="secondary" @click="generateQr">
                    {{ hasQr ? t('workshop.qr.regenerate') : t('workshop.qr.generate') }}
                </Button>
            </div>
        </div>

        <!-- Service history -->
        <div class="mt-8 max-w-2xl">
            <div class="flex items-center justify-between">
                <h2 class="text-xs font-medium uppercase tracking-wide text-ink-500">{{ t('workshop.service_history') }}</h2>
                <Link :href="`${workshopBase}/vehicle/${vehicle.id}`" class="text-sm font-medium text-ink-900 hover:underline dark:text-ink-100">
                    {{ t('common.all') }}
                </Link>
            </div>

            <p v-if="!serviceLogs.length" class="mt-3 text-sm text-ink-500">{{ t('workshop.no_history') }}</p>
            <ul v-else class="mt-3 space-y-2">
                <li
                    v-for="log in serviceLogs"
                    :key="log.id"
                    class="flex items-center justify-between rounded-card border border-ink-200 bg-white p-3 text-sm shadow-subtle dark:border-ink-800 dark:bg-ink-900"
                >
                    <div>
                        <Link :href="`${workshopBase}/${log.id}`" class="font-medium text-ink-900 hover:underline dark:text-ink-100">
                            {{ log.title }}
                        </Link>
                        <span class="ml-2 text-ink-400">{{ log.mechanic?.name }}</span>
                    </div>
                    <div class="flex items-center gap-3">
                        <StatusBadge variant="neutral" :label="t(`workshop.statuses.${log.status}`)" />
                        <span class="text-ink-500">{{ formatAUD(log.total_cost) }}</span>
                    </div>
                </li>
            </ul>
        </div>
    </AppLayout>
</template>
