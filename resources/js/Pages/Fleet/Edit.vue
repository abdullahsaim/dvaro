<script setup>
// Edit vehicle. FUNCTIONAL ONLY — design pass later.
// Status is NOT part of this form — it changes through its own endpoint
// (ChangeVehicleStatusAction) via the separate "Change status" control below.
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

const form = useForm({
    registration_number: props.vehicle.registration_number,
    make: props.vehicle.make,
    model: props.vehicle.model,
    year: props.vehicle.year,
    daily_rate: props.vehicle.daily_rate,
    insurance_company: props.vehicle.insurance_company ?? '',
    insurance_expiry: props.vehicle.insurance_expiry ?? '',
    registration_expiry: props.vehicle.registration_expiry ?? '',
    last_service_date: props.vehicle.last_service_date ?? '',
    next_service_due: props.vehicle.next_service_due ?? '',
    notes: props.vehicle.notes ?? '',
});

// Separate form for the status transition (its own endpoint / action).
const statusForm = useForm({ status: props.vehicle.status });

function submit() {
    form.put(`${base.value}/${props.vehicle.id}`);
}

function changeStatus() {
    statusForm.post(`${base.value}/${props.vehicle.id}/status`, { preserveScroll: true });
}
</script>

<template>
    <AppLayout>
        <Head :title="t('fleet.edit_vehicle')" />

        <div class="py-10">
            <div class="flex items-center justify-between">
                <h1 class="text-2xl font-semibold">{{ t('fleet.edit_vehicle') }}</h1>
                <Link :href="`${base}/${vehicle.id}`" class="text-sm text-slate-500 hover:underline dark:text-slate-400">
                    {{ t('common.back') }}
                </Link>
            </div>

            <!-- Status: separate control, NOT part of the edit form -->
            <div class="mt-6 flex max-w-2xl flex-wrap items-end gap-3 rounded border border-slate-200 p-4 dark:border-slate-800">
                <div class="flex items-center gap-2">
                    <span class="text-sm text-slate-600 dark:text-slate-300">{{ t('fleet.fields.status') }}:</span>
                    <StatusBadge :status="vehicle.status" />
                </div>
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

            <form class="mt-6 grid max-w-2xl grid-cols-1 gap-4 sm:grid-cols-2" @submit.prevent="submit">
                <label class="block">
                    <span class="text-sm text-slate-600 dark:text-slate-300">{{ t('fleet.fields.registration_number') }}</span>
                    <input v-model="form.registration_number" type="text" class="mt-1 w-full rounded border border-slate-300 px-3 py-2 dark:border-slate-700 dark:bg-slate-900" />
                    <span v-if="form.errors.registration_number" class="text-xs text-red-600">{{ form.errors.registration_number }}</span>
                </label>

                <label class="block">
                    <span class="text-sm text-slate-600 dark:text-slate-300">{{ t('fleet.fields.make') }}</span>
                    <input v-model="form.make" type="text" class="mt-1 w-full rounded border border-slate-300 px-3 py-2 dark:border-slate-700 dark:bg-slate-900" />
                    <span v-if="form.errors.make" class="text-xs text-red-600">{{ form.errors.make }}</span>
                </label>

                <label class="block">
                    <span class="text-sm text-slate-600 dark:text-slate-300">{{ t('fleet.fields.model') }}</span>
                    <input v-model="form.model" type="text" class="mt-1 w-full rounded border border-slate-300 px-3 py-2 dark:border-slate-700 dark:bg-slate-900" />
                    <span v-if="form.errors.model" class="text-xs text-red-600">{{ form.errors.model }}</span>
                </label>

                <label class="block">
                    <span class="text-sm text-slate-600 dark:text-slate-300">{{ t('fleet.fields.year') }}</span>
                    <input v-model="form.year" type="number" class="mt-1 w-full rounded border border-slate-300 px-3 py-2 dark:border-slate-700 dark:bg-slate-900" />
                    <span v-if="form.errors.year" class="text-xs text-red-600">{{ form.errors.year }}</span>
                </label>

                <label class="block">
                    <span class="text-sm text-slate-600 dark:text-slate-300">{{ t('fleet.fields.daily_rate') }}</span>
                    <input v-model="form.daily_rate" type="number" min="0" class="mt-1 w-full rounded border border-slate-300 px-3 py-2 dark:border-slate-700 dark:bg-slate-900" />
                    <span v-if="form.errors.daily_rate" class="text-xs text-red-600">{{ form.errors.daily_rate }}</span>
                </label>

                <label class="block">
                    <span class="text-sm text-slate-600 dark:text-slate-300">{{ t('fleet.fields.insurance_company') }}</span>
                    <input v-model="form.insurance_company" type="text" class="mt-1 w-full rounded border border-slate-300 px-3 py-2 dark:border-slate-700 dark:bg-slate-900" />
                </label>

                <label class="block">
                    <span class="text-sm text-slate-600 dark:text-slate-300">{{ t('fleet.fields.insurance_expiry') }}</span>
                    <input v-model="form.insurance_expiry" type="date" class="mt-1 w-full rounded border border-slate-300 px-3 py-2 dark:border-slate-700 dark:bg-slate-900" />
                </label>

                <label class="block">
                    <span class="text-sm text-slate-600 dark:text-slate-300">{{ t('fleet.fields.registration_expiry') }}</span>
                    <input v-model="form.registration_expiry" type="date" class="mt-1 w-full rounded border border-slate-300 px-3 py-2 dark:border-slate-700 dark:bg-slate-900" />
                </label>

                <label class="block">
                    <span class="text-sm text-slate-600 dark:text-slate-300">{{ t('fleet.fields.last_service_date') }}</span>
                    <input v-model="form.last_service_date" type="date" class="mt-1 w-full rounded border border-slate-300 px-3 py-2 dark:border-slate-700 dark:bg-slate-900" />
                </label>

                <label class="block">
                    <span class="text-sm text-slate-600 dark:text-slate-300">{{ t('fleet.fields.next_service_due') }}</span>
                    <input v-model="form.next_service_due" type="date" class="mt-1 w-full rounded border border-slate-300 px-3 py-2 dark:border-slate-700 dark:bg-slate-900" />
                </label>

                <label class="block sm:col-span-2">
                    <span class="text-sm text-slate-600 dark:text-slate-300">{{ t('fleet.fields.notes') }}</span>
                    <textarea v-model="form.notes" rows="3" class="mt-1 w-full rounded border border-slate-300 px-3 py-2 dark:border-slate-700 dark:bg-slate-900" />
                </label>

                <div class="sm:col-span-2">
                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="rounded bg-slate-800 px-4 py-2 text-white disabled:opacity-50 dark:bg-slate-200 dark:text-slate-900"
                    >
                        {{ t('common.save') }}
                    </button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
