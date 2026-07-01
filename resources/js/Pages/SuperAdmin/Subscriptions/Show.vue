<script setup>
// Subscription detail with tenant info. Read-only. Design-system pass.
import { Head, Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import SuperAdminLayout from '@/Layouts/SuperAdminLayout.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import StatusBadge from '@/Components/UI/StatusBadge.vue';
import { useCurrency } from '@/composables/useCurrency';

const props = defineProps({
    subscription: { type: Object, required: true },
    payments: { type: Array, required: true },
});

const { t } = useI18n();
const { formatAUD } = useCurrency();

const statusVariants = {
    active: 'success',
    trialing: 'info',
    trial: 'info',
    suspended: 'danger',
    cancelled: 'neutral',
    past_due: 'warning',
};

function formatDate(value) {
    return value ? new Date(value).toLocaleDateString('en-AU') : '—';
}
</script>

<template>
    <SuperAdminLayout>
        <Head :title="t('superadmin.subscriptions.detail_title')" />

        <Link href="/superadmin/subscriptions" class="text-sm font-medium text-ink-500 hover:underline">
            ← {{ t('superadmin.subscriptions.title') }}
        </Link>
        <PageHeader class="mt-4" :title="t('superadmin.subscriptions.detail_title')" />

        <div class="grid gap-6 md:grid-cols-2">
            <!-- Subscription -->
            <div class="rounded-card border border-ink-200 bg-white p-5 shadow-subtle dark:border-ink-800 dark:bg-ink-900">
                <dl class="space-y-2 text-sm text-ink-900 dark:text-ink-100">
                    <div class="flex justify-between">
                        <dt class="text-ink-500">{{ t('superadmin.subscriptions.tenant') }}</dt>
                        <dd>
                            <Link
                                v-if="subscription.tenant.slug"
                                :href="`/superadmin/tenants/${subscription.tenant.slug}`"
                                class="font-medium text-ink-900 hover:underline dark:text-ink-100"
                            >
                                {{ subscription.tenant.name }}
                            </Link>
                            <span v-else>{{ subscription.tenant.name ?? '—' }}</span>
                        </dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-ink-500">{{ t('superadmin.subscriptions.plan') }}</dt>
                        <dd>{{ subscription.plan?.name ?? '—' }}</dd>
                    </div>
                    <div class="flex items-center justify-between">
                        <dt class="text-ink-500">{{ t('fleet.fields.status') }}</dt>
                        <dd><StatusBadge :variant="statusVariants[subscription.status]" :label="t(`superadmin.statuses.${subscription.status}`)" /></dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-ink-500">{{ t('superadmin.subscriptions.billing_cycle') }}</dt>
                        <dd>{{ subscription.billing_cycle }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-ink-500">{{ t('superadmin.subscriptions.gateway') }}</dt>
                        <dd>{{ subscription.gateway ?? '—' }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-ink-500">{{ t('superadmin.subscriptions.gateway_id') }}</dt>
                        <dd>{{ subscription.gateway_subscription_id ?? '—' }}</dd>
                    </div>
                </dl>
            </div>

            <!-- Period + pricing -->
            <div class="rounded-card border border-ink-200 bg-white p-5 shadow-subtle dark:border-ink-800 dark:bg-ink-900">
                <dl class="space-y-2 text-sm text-ink-900 dark:text-ink-100">
                    <div class="flex justify-between">
                        <dt class="text-ink-500">{{ t('superadmin.subscriptions.period_start') }}</dt>
                        <dd>{{ formatDate(subscription.current_period_start) }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-ink-500">{{ t('superadmin.subscriptions.period_end') }}</dt>
                        <dd>{{ formatDate(subscription.current_period_end) }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-ink-500">{{ t('superadmin.subscriptions.trial_ends') }}</dt>
                        <dd>{{ formatDate(subscription.trial_ends_at) }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-ink-500">{{ t('superadmin.subscriptions.cancelled_at') }}</dt>
                        <dd>{{ formatDate(subscription.cancelled_at) }}</dd>
                    </div>
                    <div v-if="subscription.plan" class="flex justify-between border-t border-ink-100 pt-2 dark:border-ink-800">
                        <dt class="text-ink-500">{{ t('superadmin.plans.monthly') }}</dt>
                        <dd class="tabular-nums">{{ formatAUD(subscription.plan.price_monthly) }}</dd>
                    </div>
                    <div v-if="subscription.plan" class="flex justify-between">
                        <dt class="text-ink-500">{{ t('superadmin.plans.annual') }}</dt>
                        <dd class="tabular-nums">{{ formatAUD(subscription.plan.price_annual) }}</dd>
                    </div>
                </dl>
            </div>
        </div>

        <!-- Payments -->
        <h2 class="mt-10 text-lg font-semibold text-ink-900 dark:text-ink-50">{{ t('superadmin.subscriptions.payments') }}</h2>
        <p class="mt-4 text-sm text-ink-500">{{ t('superadmin.subscriptions.no_payments') }}</p>
    </SuperAdminLayout>
</template>
