<script setup>
// Vehicle detail. FUNCTIONAL ONLY — design pass later.
// Status change uses its own endpoint (ChangeVehicleStatusAction).
import { computed } from 'vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import AppLayout from '@/Layouts/AppLayout.vue';
import StatusBadge from '@/Components/StatusBadge.vue';

const props = defineProps({
    vehicle: { type: Object, required: true },
    statuses: { type: Array, required: true },
});

const { t } = useI18n();
const page = usePage();
const base = computed(() => `/app/${page.props.tenant.slug}/fleet`);
const flash = computed(() => page.props.flash?.success);

const statusForm = useForm({ status: props.vehicle.status });

function changeStatus() {
    statusForm.post(`${base.value}/${props.vehicle.id}/status`, { preserveScroll: true });
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
        </div>
    </AppLayout>
</template>
