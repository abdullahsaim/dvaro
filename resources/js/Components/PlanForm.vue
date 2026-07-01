<script setup>
// Shared plan create/edit form fields. The PARENT owns the useForm instance
// (incl. the AUD→cents transform on submit) and renders the submit button; this
// component only renders the fields bound to the passed-in reactive form object.
// Design-system pass.
import { useI18n } from 'vue-i18n';
import Input from '@/Components/UI/Input.vue';
import Textarea from '@/Components/UI/Textarea.vue';

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
        <Input v-model="form.name" required :label="t('superadmin.plans.name')" :error="form.errors.name" />
        <Textarea v-model="form.description" :rows="2" :label="t('superadmin.plans.description')" :error="form.errors.description" />

        <!-- Pricing (AUD) -->
        <div class="grid grid-cols-2 gap-4">
            <Input v-model.number="form.price_monthly" type="number" min="0" step="0.01" :label="t('superadmin.plans.price_monthly_aud')" :error="form.errors.price_monthly" />
            <Input v-model.number="form.price_annual" type="number" min="0" step="0.01" :label="t('superadmin.plans.price_annual_aud')" :error="form.errors.price_annual" />
        </div>

        <!-- Trial + sort -->
        <div class="grid grid-cols-2 gap-4">
            <Input v-model.number="form.trial_days" type="number" min="0" :label="t('superadmin.plans.trial_days')" :error="form.errors.trial_days" />
            <Input v-model.number="form.sort_order" type="number" min="0" :label="t('superadmin.plans.sort_order')" />
        </div>

        <!-- Toggles -->
        <div class="space-y-2">
            <label class="flex items-center gap-2 text-sm text-ink-700 dark:text-ink-300">
                <input v-model="form.is_active" type="checkbox" class="h-4 w-4 rounded border-ink-300 text-ink-900 accent-ink-900 dark:border-ink-700 dark:accent-ink-100" />
                {{ t('superadmin.plans.is_active') }}
            </label>
            <label class="flex items-center gap-2 text-sm text-ink-700 dark:text-ink-300">
                <input v-model="form.is_free" type="checkbox" class="h-4 w-4 rounded border-ink-300 text-ink-900 accent-ink-900 dark:border-ink-700 dark:accent-ink-100" />
                {{ t('superadmin.plans.is_free') }}
            </label>
        </div>

        <!-- Modules -->
        <div>
            <p class="text-sm font-medium text-ink-700 dark:text-ink-300">{{ t('superadmin.plans.modules') }}</p>
            <div class="mt-2 grid grid-cols-2 gap-2 sm:grid-cols-3">
                <label v-for="key in moduleKeys" :key="key" class="flex items-center gap-2 text-sm text-ink-700 dark:text-ink-300">
                    <input
                        type="checkbox"
                        class="h-4 w-4 rounded border-ink-300 accent-ink-900 dark:border-ink-700 dark:accent-ink-100"
                        :checked="form.modules.includes(key)"
                        @change="toggleModule(key)"
                    />
                    {{ t(`superadmin.plans.module_keys.${key}`) }}
                </label>
            </div>
        </div>

        <!-- Limits -->
        <div>
            <p class="text-sm font-medium text-ink-700 dark:text-ink-300">{{ t('superadmin.plans.limits') }}</p>
            <p class="text-xs text-ink-400">{{ t('superadmin.plans.limit_hint') }}</p>
            <div class="mt-2 grid grid-cols-2 gap-4 sm:grid-cols-3">
                <Input
                    v-for="key in limitKeys"
                    :key="key"
                    v-model.number="form.limits[key]"
                    type="number"
                    min="-1"
                    :label="t(`superadmin.plans.limit_keys.${key}`)"
                />
            </div>
        </div>
    </div>
</template>
