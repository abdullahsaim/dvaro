<script setup>
// Edit vehicle — design-system pass. Status is NOT part of this form — it
// changes through its own endpoint (ChangeVehicleStatusAction) via the separate
// "Change status" control below.
import { computed } from 'vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import StatusBadge from '@/Components/UI/StatusBadge.vue';
import Button from '@/Components/UI/Button.vue';
import Input from '@/Components/UI/Input.vue';
import Select from '@/Components/UI/Select.vue';
import Textarea from '@/Components/UI/Textarea.vue';

const props = defineProps({
    vehicle: { type: Object, required: true },
    statuses: { type: Array, required: true },
});

const { t } = useI18n();
const page = usePage();
const base = computed(() => `/app/${page.props.tenant.slug}/fleet`);

const statusVariants = {
    available: 'success',
    rented: 'info',
    maintenance: 'warning',
    suspended: 'neutral',
    accident: 'danger',
    reserved: 'neutral',
};

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

        <PageHeader :title="t('fleet.edit_vehicle')">
            <template #actions>
                <Button variant="ghost" @click="router.visit(`${base}/${vehicle.id}`)">{{ t('common.back') }}</Button>
            </template>
        </PageHeader>

        <!-- Status: separate control, NOT part of the edit form -->
        <div class="mb-6 flex max-w-2xl flex-wrap items-end gap-3 rounded-card border border-ink-200 bg-white p-4 shadow-subtle dark:border-ink-800 dark:bg-ink-900">
            <div class="flex items-center gap-2">
                <span class="text-sm text-ink-500">{{ t('fleet.fields.status') }}:</span>
                <StatusBadge :variant="statusVariants[vehicle.status]" :label="t(`fleet.statuses.${vehicle.status}`)" />
            </div>
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

        <form class="grid max-w-2xl grid-cols-1 gap-4 sm:grid-cols-2" @submit.prevent="submit">
            <Input v-model="form.registration_number" :label="t('fleet.fields.registration_number')" :error="form.errors.registration_number" />
            <Input v-model="form.make" :label="t('fleet.fields.make')" :error="form.errors.make" />
            <Input v-model="form.model" :label="t('fleet.fields.model')" :error="form.errors.model" />
            <Input v-model="form.year" type="number" :label="t('fleet.fields.year')" :error="form.errors.year" />
            <Input v-model="form.daily_rate" type="number" :label="t('fleet.fields.daily_rate')" :error="form.errors.daily_rate" />
            <Input v-model="form.insurance_company" :label="t('fleet.fields.insurance_company')" :error="form.errors.insurance_company" />
            <Input v-model="form.insurance_expiry" type="date" :label="t('fleet.fields.insurance_expiry')" :error="form.errors.insurance_expiry" />
            <Input v-model="form.registration_expiry" type="date" :label="t('fleet.fields.registration_expiry')" :error="form.errors.registration_expiry" />
            <Input v-model="form.last_service_date" type="date" :label="t('fleet.fields.last_service_date')" :error="form.errors.last_service_date" />
            <Input v-model="form.next_service_due" type="date" :label="t('fleet.fields.next_service_due')" :error="form.errors.next_service_due" />

            <div class="sm:col-span-2">
                <Textarea v-model="form.notes" :rows="3" :label="t('fleet.fields.notes')" :error="form.errors.notes" />
            </div>

            <div class="sm:col-span-2">
                <Button type="submit" :loading="form.processing">{{ t('common.save') }}</Button>
            </div>
        </form>
    </AppLayout>
</template>
