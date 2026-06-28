<script setup>
// Platform settings form. PUT /superadmin/settings. FUNCTIONAL ONLY.
import { Head, useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import SuperAdminLayout from '@/Layouts/SuperAdminLayout.vue';

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

function submit() {
    form.put('/superadmin/settings', { preserveScroll: true });
}
</script>

<template>
    <SuperAdminLayout>
        <Head :title="t('superadmin.settings.title')" />

        <div class="py-10">
            <h1 class="text-2xl font-semibold">{{ t('superadmin.settings.title') }}</h1>

            <form class="mt-6 max-w-2xl space-y-8" @submit.prevent="submit">
                <!-- General -->
                <section>
                    <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                        {{ t('superadmin.settings.general') }}
                    </h2>
                    <div class="mt-3 space-y-4">
                        <div>
                            <label class="block text-sm font-medium">{{ t('superadmin.settings.platform_name') }}</label>
                            <input
                                v-model="form.platform_name"
                                type="text"
                                required
                                class="mt-1 w-full rounded border border-slate-300 px-3 py-2 dark:border-slate-700 dark:bg-slate-900"
                            />
                            <p v-if="form.errors.platform_name" class="mt-1 text-sm text-red-600">{{ form.errors.platform_name }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium">{{ t('superadmin.settings.support_email') }}</label>
                            <input
                                v-model="form.support_email"
                                type="email"
                                required
                                class="mt-1 w-full rounded border border-slate-300 px-3 py-2 dark:border-slate-700 dark:bg-slate-900"
                            />
                            <p v-if="form.errors.support_email" class="mt-1 text-sm text-red-600">{{ form.errors.support_email }}</p>
                        </div>
                    </div>
                </section>

                <!-- Signups & trials -->
                <section>
                    <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                        {{ t('superadmin.settings.signups') }}
                    </h2>
                    <div class="mt-3 space-y-4">
                        <label class="flex items-center gap-2 text-sm">
                            <input v-model="form.manual_tenant_approval" type="checkbox" />
                            {{ t('superadmin.settings.manual_tenant_approval') }}
                        </label>
                        <label class="flex items-center gap-2 text-sm">
                            <input v-model="form.free_trial_enabled" type="checkbox" />
                            {{ t('superadmin.settings.free_trial_enabled') }}
                        </label>
                        <div>
                            <label class="block text-sm font-medium">{{ t('superadmin.settings.free_trial_days') }}</label>
                            <input
                                v-model.number="form.free_trial_days"
                                type="number"
                                min="0"
                                class="mt-1 w-40 rounded border border-slate-300 px-3 py-2 dark:border-slate-700 dark:bg-slate-900"
                            />
                            <p v-if="form.errors.free_trial_days" class="mt-1 text-sm text-red-600">{{ form.errors.free_trial_days }}</p>
                        </div>
                        <label class="flex items-center gap-2 text-sm">
                            <input v-model="form.freemium_enabled" type="checkbox" />
                            {{ t('superadmin.settings.freemium_enabled') }}
                        </label>
                        <div>
                            <label class="block text-sm font-medium">{{ t('superadmin.settings.default_plan_id') }}</label>
                            <select
                                v-model="form.default_plan_id"
                                class="mt-1 w-64 rounded border border-slate-300 px-3 py-2 dark:border-slate-700 dark:bg-slate-900"
                            >
                                <option :value="null">{{ t('superadmin.settings.no_default_plan') }}</option>
                                <option v-for="p in plans" :key="p.id" :value="p.id">{{ p.name }}</option>
                            </select>
                            <p v-if="form.errors.default_plan_id" class="mt-1 text-sm text-red-600">{{ form.errors.default_plan_id }}</p>
                        </div>
                    </div>
                </section>

                <!-- Limits & maintenance -->
                <section>
                    <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                        {{ t('superadmin.settings.limits') }}
                    </h2>
                    <div class="mt-3 space-y-4">
                        <div>
                            <label class="block text-sm font-medium">{{ t('superadmin.settings.max_tenants') }}</label>
                            <input
                                v-model.number="form.max_tenants"
                                type="number"
                                min="0"
                                class="mt-1 w-40 rounded border border-slate-300 px-3 py-2 dark:border-slate-700 dark:bg-slate-900"
                            />
                            <p v-if="form.errors.max_tenants" class="mt-1 text-sm text-red-600">{{ form.errors.max_tenants }}</p>
                        </div>
                        <label class="flex items-center gap-2 text-sm">
                            <input v-model="form.maintenance_mode" type="checkbox" />
                            {{ t('superadmin.settings.maintenance_mode') }}
                        </label>
                    </div>
                </section>

                <button
                    type="submit"
                    :disabled="form.processing"
                    class="rounded bg-indigo-600 px-4 py-2 text-white disabled:opacity-50"
                >
                    {{ t('common.save') }}
                </button>
            </form>
        </div>
    </SuperAdminLayout>
</template>
