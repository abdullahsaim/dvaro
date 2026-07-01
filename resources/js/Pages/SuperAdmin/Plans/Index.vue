<script setup>
// Plan list — pricing, modules, limits, active toggle, edit. Design-system pass.
import { Head, Link, router } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import SuperAdminLayout from '@/Layouts/SuperAdminLayout.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import StatusBadge from '@/Components/UI/StatusBadge.vue';
import Button from '@/Components/UI/Button.vue';
import { useCurrency } from '@/composables/useCurrency';

const props = defineProps({
    plans: { type: Array, required: true },
});

const { t } = useI18n();
const { formatAUD } = useCurrency();

function toggle(plan) {
    router.post(`/superadmin/plans/${plan.id}/toggle`, {}, { preserveScroll: true });
}
</script>

<template>
    <SuperAdminLayout>
        <Head :title="t('superadmin.plans.title')" />

        <PageHeader :title="t('superadmin.plans.title')">
            <template #actions>
                <Button @click="router.visit('/superadmin/plans/create')">{{ t('superadmin.plans.new_plan') }}</Button>
            </template>
        </PageHeader>

        <p v-if="!plans.length" class="text-sm text-ink-500">{{ t('superadmin.plans.empty') }}</p>

        <div v-else class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
            <div
                v-for="plan in plans"
                :key="plan.id"
                class="flex flex-col rounded-card border border-ink-200 bg-white p-5 shadow-subtle dark:border-ink-800 dark:bg-ink-900"
            >
                <div class="flex items-start justify-between">
                    <div>
                        <h2 class="text-lg font-semibold text-ink-900 dark:text-ink-50">{{ plan.name }}</h2>
                        <p v-if="plan.is_free" class="text-xs text-success-600 dark:text-success-500">{{ t('superadmin.plans.is_free') }}</p>
                    </div>
                    <StatusBadge
                        :variant="plan.is_active ? 'success' : 'neutral'"
                        :label="plan.is_active ? t('superadmin.plans.active_badge') : t('superadmin.plans.inactive_badge')"
                    />
                </div>

                <p v-if="plan.description" class="mt-2 text-sm text-ink-500">{{ plan.description }}</p>

                <div class="mt-4 space-y-1 text-sm text-ink-700 dark:text-ink-200">
                    <p>{{ t('superadmin.plans.monthly') }}: <span class="font-medium">{{ formatAUD(plan.price_monthly) }}</span></p>
                    <p>{{ t('superadmin.plans.annual') }}: <span class="font-medium">{{ formatAUD(plan.price_annual) }}</span></p>
                    <p class="text-ink-500">{{ t('superadmin.plans.trial_days') }}: {{ plan.trial_days }}</p>
                </div>

                <div v-if="plan.modules?.length" class="mt-4 flex flex-wrap gap-1">
                    <span v-for="m in plan.modules" :key="m" class="rounded bg-ink-100 px-2 py-0.5 text-xs text-ink-600 dark:bg-ink-800 dark:text-ink-300">
                        {{ t(`superadmin.plans.module_keys.${m}`) }}
                    </span>
                </div>

                <p class="mt-4 text-xs text-ink-400">
                    {{ t('superadmin.plans.subscriptions_count', { count: plan.subscriptions_count }) }}
                </p>

                <div class="mt-4 flex gap-3 border-t border-ink-100 pt-4 text-sm dark:border-ink-800">
                    <Link :href="`/superadmin/plans/${plan.id}/edit`" class="font-medium text-ink-900 hover:underline dark:text-ink-100">
                        {{ t('superadmin.plans.edit') }}
                    </Link>
                    <button type="button" class="text-ink-500 hover:underline" @click="toggle(plan)">
                        {{ t('superadmin.plans.toggle') }}
                    </button>
                </div>
            </div>
        </div>
    </SuperAdminLayout>
</template>
