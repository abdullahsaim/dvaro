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
