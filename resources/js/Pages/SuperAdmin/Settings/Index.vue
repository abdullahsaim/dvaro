<script setup>
// Platform settings form. PUT /superadmin/settings. Design-system pass.
import { Head, useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import SuperAdminLayout from '@/Layouts/SuperAdminLayout.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import Button from '@/Components/UI/Button.vue';
import Input from '@/Components/UI/Input.vue';
import Select from '@/Components/UI/Select.vue';

const props = defineProps({
    settings: { type: Object, required: true },
    plans: { type: Array, required: true },
});

const { t } = useI18n();

const form = useForm({
    platform_name: props.settings.platform_name ?? '',
    support_email: props.settings.support_email ?? '',
    manual_tenant_approval: !!props.settings.manual_tenant_approval,
    free_trial_enabled: !!props.settings.free_trial_enabled,
    free_trial_days: props.settings.free_trial_days ?? 0,
    freemium_enabled: !!props.settings.freemium_enabled,
    default_plan_id: props.settings.default_plan_id ?? null,
    max_tenants: props.settings.max_tenants ?? null,
    maintenance_mode: !!props.settings.maintenance_mode,
});

const checkbox = 'h-4 w-4 rounded border-ink-300 accent-ink-900 dark:border-ink-700 dark:accent-ink-100';

function submit() {
    form.put('/superadmin/settings', { preserveScroll: true });
}
</script>

<template>
    <SuperAdminLayout>
        <Head :title="t('superadmin.settings.title')" />

        <PageHeader :title="t('superadmin.settings.title')" />

        <form class="max-w-2xl space-y-8" @submit.prevent="submit">
            <!-- General -->
            <section>
                <h2 class="text-xs font-semibold uppercase tracking-wide text-ink-500">{{ t('superadmin.settings.general') }}</h2>
                <div class="mt-3 space-y-4">
                    <Input v-model="form.platform_name" required :label="t('superadmin.settings.platform_name')" :error="form.errors.platform_name" />
                    <Input v-model="form.support_email" type="email" required :label="t('superadmin.settings.support_email')" :error="form.errors.support_email" />
                </div>
            </section>

            <!-- Signups & trials -->
            <section>
                <h2 class="text-xs font-semibold uppercase tracking-wide text-ink-500">{{ t('superadmin.settings.signups') }}</h2>
                <div class="mt-3 space-y-4">
                    <label class="flex items-center gap-2 text-sm text-ink-700 dark:text-ink-300">
                        <input v-model="form.manual_tenant_approval" type="checkbox" :class="checkbox" />
                        {{ t('superadmin.settings.manual_tenant_approval') }}
                    </label>
                    <label class="flex items-center gap-2 text-sm text-ink-700 dark:text-ink-300">
                        <input v-model="form.free_trial_enabled" type="checkbox" :class="checkbox" />
                        {{ t('superadmin.settings.free_trial_enabled') }}
                    </label>
                    <Input v-model.number="form.free_trial_days" type="number" min="0" :label="t('superadmin.settings.free_trial_days')" :error="form.errors.free_trial_days" class="w-40" />
                    <label class="flex items-center gap-2 text-sm text-ink-700 dark:text-ink-300">
                        <input v-model="form.freemium_enabled" type="checkbox" :class="checkbox" />
                        {{ t('superadmin.settings.freemium_enabled') }}
                    </label>
                    <Select v-model="form.default_plan_id" :label="t('superadmin.settings.default_plan_id')" :error="form.errors.default_plan_id" class="w-64">
                        <option :value="null">{{ t('superadmin.settings.no_default_plan') }}</option>
                        <option v-for="p in plans" :key="p.id" :value="p.id">{{ p.name }}</option>
                    </Select>
                </div>
            </section>

            <!-- Limits & maintenance -->
            <section>
                <h2 class="text-xs font-semibold uppercase tracking-wide text-ink-500">{{ t('superadmin.settings.limits') }}</h2>
                <div class="mt-3 space-y-4">
                    <Input v-model.number="form.max_tenants" type="number" min="0" :label="t('superadmin.settings.max_tenants')" :error="form.errors.max_tenants" class="w-40" />
                    <label class="flex items-center gap-2 text-sm text-ink-700 dark:text-ink-300">
                        <input v-model="form.maintenance_mode" type="checkbox" :class="checkbox" />
                        {{ t('superadmin.settings.maintenance_mode') }}
                    </label>
                </div>
            </section>

            <Button type="submit" :loading="form.processing">{{ t('common.save') }}</Button>
        </form>
    </SuperAdminLayout>
</template>
