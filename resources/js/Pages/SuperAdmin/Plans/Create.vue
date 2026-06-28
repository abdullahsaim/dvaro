<script setup>
// Create a plan. Prices entered in AUD, converted to cents on submit.
import { Head, Link, useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import SuperAdminLayout from '@/Layouts/SuperAdminLayout.vue';
import PlanForm from '@/Components/PlanForm.vue';

const props = defineProps({
    moduleKeys: { type: Array, required: true },
    limitKeys: { type: Array, required: true },
});

const { t } = useI18n();

const form = useForm({
    name: '',
    description: '',
    price_monthly: 0,
    price_annual: 0,
    trial_days: 0,
    sort_order: 0,
    is_active: true,
    is_free: false,
    modules: [],
    limits: {},
});

function submit() {
    form
        .transform((data) => ({
            ...data,
            price_monthly: Math.round((Number(data.price_monthly) || 0) * 100),
            price_annual: Math.round((Number(data.price_annual) || 0) * 100),
            limits: cleanLimits(data.limits),
        }))
        .post('/superadmin/plans');
}

// Drop blank limit fields so an absent key means "unlimited" (Plan::getLimit).
function cleanLimits(limits) {
    const out = {};
    Object.entries(limits).forEach(([k, v]) => {
        if (v !== '' && v !== null && v !== undefined) {
            out[k] = v;
        }
    });
    return out;
}
</script>

<template>
    <SuperAdminLayout>
        <Head :title="t('superadmin.plans.new_plan')" />

        <div class="py-10">
            <Link href="/superadmin/plans" class="text-sm text-indigo-600 hover:underline dark:text-indigo-400">
                ← {{ t('superadmin.plans.title') }}
            </Link>
            <h1 class="mt-4 text-2xl font-semibold">{{ t('superadmin.plans.new_plan') }}</h1>

            <form class="mt-6 max-w-3xl" @submit.prevent="submit">
                <PlanForm :form="form" :module-keys="moduleKeys" :limit-keys="limitKeys" />

                <div class="mt-8 flex gap-3">
                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="rounded bg-indigo-600 px-4 py-2 text-white disabled:opacity-50"
                    >
                        {{ t('common.save') }}
                    </button>
                    <Link href="/superadmin/plans" class="rounded border border-slate-300 px-4 py-2 dark:border-slate-700">
                        {{ t('common.cancel') }}
                    </Link>
                </div>
            </form>
        </div>
    </SuperAdminLayout>
</template>
