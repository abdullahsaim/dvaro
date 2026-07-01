<script setup>
// Edit a plan. Prices shown/edited in AUD, stored cents converted back here.
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import SuperAdminLayout from '@/Layouts/SuperAdminLayout.vue';
import PlanForm from '@/Components/PlanForm.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import Button from '@/Components/UI/Button.vue';

const props = defineProps({
    plan: { type: Object, required: true },
    moduleKeys: { type: Array, required: true },
    limitKeys: { type: Array, required: true },
});

const { t } = useI18n();

const form = useForm({
    name: props.plan.name,
    description: props.plan.description ?? '',
    price_monthly: (props.plan.price_monthly ?? 0) / 100,
    price_annual: (props.plan.price_annual ?? 0) / 100,
    trial_days: props.plan.trial_days ?? 0,
    sort_order: props.plan.sort_order ?? 0,
    is_active: !!props.plan.is_active,
    is_free: !!props.plan.is_free,
    modules: [...(props.plan.modules ?? [])],
    // Spread existing limits so the bound number inputs show current values.
    limits: { ...(props.plan.limits ?? {}) },
});

function submit() {
    form
        .transform((data) => ({
            ...data,
            price_monthly: Math.round((Number(data.price_monthly) || 0) * 100),
            price_annual: Math.round((Number(data.price_annual) || 0) * 100),
            limits: cleanLimits(data.limits),
        }))
        .put(`/superadmin/plans/${props.plan.id}`);
}

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
        <Head :title="t('superadmin.plans.edit_plan')" />

        <Link href="/superadmin/plans" class="text-sm font-medium text-ink-500 hover:underline">
            ← {{ t('superadmin.plans.title') }}
        </Link>
        <PageHeader class="mt-4" :title="t('superadmin.plans.edit_plan')" />

        <form class="max-w-3xl" @submit.prevent="submit">
            <PlanForm :form="form" :module-keys="moduleKeys" :limit-keys="limitKeys" />

            <div class="mt-8 flex gap-3">
                <Button type="submit" :loading="form.processing">{{ t('common.save') }}</Button>
                <Button variant="secondary" @click="router.visit('/superadmin/plans')">{{ t('common.cancel') }}</Button>
            </div>
        </form>
    </SuperAdminLayout>
</template>
