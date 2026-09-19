<script setup>
// New vehicle form — design-system pass.
import { computed } from 'vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import Button from '@/Components/UI/Button.vue';
import Input from '@/Components/UI/Input.vue';
import Select from '@/Components/UI/Select.vue';
import Textarea from '@/Components/UI/Textarea.vue';

const props = defineProps({
    statuses: { type: Array, required: true },
});

const { t } = useI18n();
const page = usePage();
const base = computed(() => `/app/${page.props.tenant.slug}/fleet`);

const form = useForm({
    registration_number: '',
    make: '',
    model: '',
    year: new Date().getFullYear(),
    status: 'available',
    daily_rate: 0,
    insurance_company: '',
    insurance_expiry: '',
    registration_expiry: '',
    last_service_date: '',
    next_service_due: '',
    notes: '',
    service_interval_months: '',
    service_interval_km: '',
    current_odometer: '',
    last_service_odometer: '',
});

// Next service date is derived server-side (last service + months interval)
// whenever both are set — the manual field is disabled in that case.
const autoNextDue = computed(() => !!form.service_interval_months && !!form.last_service_date);

function submit() {
    form.post(base.value);
}
</script>

<template>
    <AppLayout>
        <Head :title="t('fleet.new_vehicle')" />

        <PageHeader :title="t('fleet.new_vehicle')">
            <template #actions>
                <Button variant="ghost" @click="router.visit(base)">{{ t('common.back') }}</Button>
            </template>
        </PageHeader>

        <form class="grid max-w-2xl grid-cols-1 gap-4 sm:grid-cols-2" @submit.prevent="submit">
            <Input v-model="form.registration_number" :label="t('fleet.fields.registration_number')" :error="form.errors.registration_number" />
            <Select v-model="form.status" :label="t('fleet.fields.status')" :error="form.errors.status">
                <option v-for="s in statuses" :key="s" :value="s">{{ t(`fleet.statuses.${s}`) }}</option>
            </Select>
            <Input v-model="form.make" :label="t('fleet.fields.make')" :error="form.errors.make" />
            <Input v-model="form.model" :label="t('fleet.fields.model')" :error="form.errors.model" />
            <Input v-model="form.year" type="number" :label="t('fleet.fields.year')" :error="form.errors.year" />
            <Input v-model="form.daily_rate" type="number" :label="t('fleet.fields.daily_rate')" :error="form.errors.daily_rate" />
            <Input v-model="form.insurance_company" :label="t('fleet.fields.insurance_company')" :error="form.errors.insurance_company" />
            <Input v-model="form.insurance_expiry" type="date" :label="t('fleet.fields.insurance_expiry')" :error="form.errors.insurance_expiry" />
            <Input v-model="form.registration_expiry" type="date" :label="t('fleet.fields.registration_expiry')" :error="form.errors.registration_expiry" />
            <Input v-model="form.last_service_date" type="date" :label="t('fleet.fields.last_service_date')" :error="form.errors.last_service_date" />
            <Input v-model="form.next_service_due" type="date" :label="t('fleet.fields.next_service_due')" :error="form.errors.next_service_due" :disabled="autoNextDue" :help="autoNextDue ? t('fleet.schedule.auto_hint') : ''" />

            <!-- Service schedule: whichever of months / km comes first -->
            <fieldset class="rounded-card border border-ink-200 p-4 sm:col-span-2 dark:border-ink-800">
                <legend class="px-1 text-sm font-medium text-ink-900 dark:text-ink-100">{{ t('fleet.schedule.title') }}</legend>
                <p class="mb-4 text-xs text-ink-500">{{ t('fleet.schedule.hint') }}</p>
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <Input v-model="form.service_interval_months" type="number" min="1" :label="t('fleet.fields.service_interval_months')" :error="form.errors.service_interval_months" />
                    <Input v-model="form.service_interval_km" type="number" min="100" step="100" :label="t('fleet.fields.service_interval_km')" :error="form.errors.service_interval_km" />
                    <Input v-model="form.current_odometer" type="number" min="0" :label="t('fleet.fields.current_odometer')" :error="form.errors.current_odometer" />
                    <Input v-model="form.last_service_odometer" type="number" min="0" :label="t('fleet.fields.last_service_odometer')" :error="form.errors.last_service_odometer" />
                </div>
            </fieldset>

            <div class="sm:col-span-2">
                <Textarea v-model="form.notes" :rows="3" :label="t('fleet.fields.notes')" :error="form.errors.notes" />
            </div>

            <div class="sm:col-span-2">
                <Button type="submit" :loading="form.processing">{{ t('common.save') }}</Button>
            </div>
        </form>
    </AppLayout>
</template>
