<script setup>
// Book a future workshop appointment (tenant-admin). The vehicle stays in
// service until work actually starts — see ScheduleServiceAction.
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
    vehicles: { type: Array, required: true },
    mechanics: { type: Array, required: true },
});

const { t } = useI18n();
const page = usePage();
const base = computed(() => `/app/${page.props.tenant.slug}/workshop`);

const form = useForm({
    vehicle_id: '',
    mechanic_id: '',
    title: '',
    description: '',
    scheduled_for: '',
});

function submit() {
    form.post(`${base.value}/schedule`);
}
</script>

<template>
    <AppLayout>
        <Head :title="t('workshop.schedule.title')" />

        <PageHeader :title="t('workshop.schedule.title')" :description="t('workshop.schedule.subtitle')">
            <template #actions>
                <Button variant="ghost" @click="router.visit(base)">{{ t('common.back') }}</Button>
            </template>
        </PageHeader>

        <div class="max-w-xl rounded-card border border-ink-200 bg-white p-6 shadow-subtle dark:border-ink-800 dark:bg-ink-900">
            <form class="space-y-4" @submit.prevent="submit">
                <Select v-model="form.vehicle_id" :label="t('workshop.schedule.vehicle')" :error="form.errors.vehicle_id" required>
                    <option value="" disabled>{{ t('workshop.schedule.select_vehicle') }}</option>
                    <option v-for="v in vehicles" :key="v.id" :value="v.id">
                        {{ v.make }} {{ v.model }} · {{ v.registration_number }}
                    </option>
                </Select>

                <Select v-model="form.mechanic_id" :label="t('workshop.schedule.mechanic')" :error="form.errors.mechanic_id" required>
                    <option value="" disabled>{{ t('workshop.schedule.select_mechanic') }}</option>
                    <option v-for="m in mechanics" :key="m.id" :value="m.id">{{ m.name }}</option>
                </Select>

                <Input v-model="form.title" :label="t('workshop.fields.title')" :error="form.errors.title" required />

                <Textarea v-model="form.description" :label="t('workshop.fields.description')" :error="form.errors.description" />

                <Input
                    v-model="form.scheduled_for"
                    type="datetime-local"
                    :label="t('workshop.schedule.scheduled_for')"
                    :error="form.errors.scheduled_for"
                    required
                />

                <Button type="submit" :loading="form.processing">{{ t('workshop.schedule.submit') }}</Button>
            </form>
        </div>
    </AppLayout>
</template>
