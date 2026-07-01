<script setup>
// Create a plan. Prices entered in AUD, converted to cents on submit.
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import SuperAdminLayout from '@/Layouts/SuperAdminLayout.vue';
import PlanForm from '@/Components/PlanForm.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import Button from '@/Components/UI/Button.vue';

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

        <Link href="/superadmin/plans" class="text-sm font-medium text-ink-500 hover:underline">
            ← {{ t('superadmin.plans.title') }}
        </Link>
        <PageHeader class="mt-4" :title="t('superadmin.plans.new_plan')" />

        <form class="max-w-3xl" @submit.prevent="submit">
            <PlanForm :form="form" :module-keys="moduleKeys" :limit-keys="limitKeys" />

            <div class="mt-8 flex gap-3">
                <Button type="submit" :loading="form.processing">{{ t('common.save') }}</Button>
                <Button variant="secondary" @click="router.visit('/superadmin/plans')">{{ t('common.cancel') }}</Button>
            </div>
        </form>
    </SuperAdminLayout>
</template>
