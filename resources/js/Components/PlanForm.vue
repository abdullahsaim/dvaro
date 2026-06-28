<script setup>
// Shared plan create/edit form fields. The PARENT owns the useForm instance
// (incl. the AUD→cents transform on submit) and renders the submit button; this
// component only renders the fields bound to the passed-in reactive form object.
// FUNCTIONAL ONLY — design pass later.
import { useI18n } from 'vue-i18n';

const props = defineProps({
    form: { type: Object, required: true },
    moduleKeys: { type: Array, required: true },
    limitKeys: { type: Array, required: true },
});

const { t } = useI18n();

function toggleModule(key) {
    const i = props.form.modules.indexOf(key);
    if (i === -1) {
        props.form.modules.push(key);
    } else {
        props.form.modules.splice(i, 1);
    }
}
</script>

<template>
    <div class="space-y-6">
        <!-- Name + description -->
        <div>
            <label class="block text-sm font-medium">{{ t('superadmin.plans.name') }}</label>
            <input
                v-model="form.name"
                type="text"
                required
                class="mt-1 w-full rounded border border-slate-300 px-3 py-2 dark:border-slate-700 dark:bg-slate-900"
            />
            <p v-if="form.errors.name" class="mt-1 text-sm text-red-600">{{ form.errors.name }}</p>
        </div>

        <div>
            <label class="block text-sm font-medium">{{ t('superadmin.plans.description') }}</label>
            <textarea
                v-model="form.description"
                rows="2"
                class="mt-1 w-full rounded border border-slate-300 px-3 py-2 dark:border-slate-700 dark:bg-slate-900"
            />
        </div>

        <!-- Pricing (AUD) -->
        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium">{{ t('superadmin.plans.price_monthly_aud') }}</label>
                <input
                    v-model.number="form.price_monthly"
                    type="number"
                    min="0"
                    step="0.01"
                    class="mt-1 w-full rounded border border-slate-300 px-3 py-2 dark:border-slate-700 dark:bg-slate-900"
                />
                <p v-if="form.errors.price_monthly" class="mt-1 text-sm text-red-600">{{ form.errors.price_monthly }}</p>
            </div>
            <div>
                <label class="block text-sm font-medium">{{ t('superadmin.plans.price_annual_aud') }}</label>
                <input
                    v-model.number="form.price_annual"
                    type="number"
                    min="0"
                    step="0.01"
                    class="mt-1 w-full rounded border border-slate-300 px-3 py-2 dark:border-slate-700 dark:bg-slate-900"
                />
                <p v-if="form.errors.price_annual" class="mt-1 text-sm text-red-600">{{ form.errors.price_annual }}</p>
            </div>
        </div>

        <!-- Trial + sort -->
        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium">{{ t('superadmin.plans.trial_days') }}</label>
                <input
                    v-model.number="form.trial_days"
                    type="number"
                    min="0"
                    class="mt-1 w-full rounded border border-slate-300 px-3 py-2 dark:border-slate-700 dark:bg-slate-900"
                />
                <p v-if="form.errors.trial_days" class="mt-1 text-sm text-red-600">{{ form.errors.trial_days }}</p>
            </div>
            <div>
                <label class="block text-sm font-medium">{{ t('superadmin.plans.sort_order') }}</label>
                <input
                    v-model.number="form.sort_order"
                    type="number"
                    min="0"
                    class="mt-1 w-full rounded border border-slate-300 px-3 py-2 dark:border-slate-700 dark:bg-slate-900"
                />
            </div>
        </div>

        <!-- Toggles -->
        <div class="space-y-2">
            <label class="flex items-center gap-2 text-sm">
                <input v-model="form.is_active" type="checkbox" />
                {{ t('superadmin.plans.is_active') }}
            </label>
            <label class="flex items-center gap-2 text-sm">
                <input v-model="form.is_free" type="checkbox" />
                {{ t('superadmin.plans.is_free') }}
            </label>
        </div>

        <!-- Modules -->
        <div>
            <p class="text-sm font-medium">{{ t('superadmin.plans.modules') }}</p>
            <div class="mt-2 grid grid-cols-2 gap-2 sm:grid-cols-3">
                <label v-for="key in moduleKeys" :key="key" class="flex items-center gap-2 text-sm">
                    <input
                        type="checkbox"
                        :checked="form.modules.includes(key)"
                        @change="toggleModule(key)"
                    />
                    {{ t(`superadmin.plans.module_keys.${key}`) }}
                </label>
            </div>
        </div>

        <!-- Limits -->
        <div>
            <p class="text-sm font-medium">{{ t('superadmin.plans.limits') }}</p>
            <p class="text-xs text-slate-400">{{ t('superadmin.plans.limit_hint') }}</p>
            <div class="mt-2 grid grid-cols-2 gap-4 sm:grid-cols-3">
                <div v-for="key in limitKeys" :key="key">
                    <label class="block text-xs text-slate-500 dark:text-slate-400">
                        {{ t(`superadmin.plans.limit_keys.${key}`) }}
                    </label>
                    <input
                        v-model.number="form.limits[key]"
                        type="number"
                        min="-1"
                        class="mt-1 w-full rounded border border-slate-300 px-3 py-2 dark:border-slate-700 dark:bg-slate-900"
                    />
                </div>
            </div>
        </div>
    </div>
</template>
