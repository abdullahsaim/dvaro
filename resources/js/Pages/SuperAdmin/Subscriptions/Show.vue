<script setup>
// Subscription detail with tenant info. Read-only. FUNCTIONAL ONLY.
import { Head, Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import SuperAdminLayout from '@/Layouts/SuperAdminLayout.vue';
import { useCurrency } from '@/composables/useCurrency';

const props = defineProps({
    subscription: { type: Object, required: true },
    payments: { type: Array, required: true },
});

const { t } = useI18n();
const { formatAUD } = useCurrency();

function formatDate(value) {
    return value ? new Date(value).toLocaleDateString('en-AU') : '—';
}
</script>

<template>
    <SuperAdminLayout>
        <Head :title="t('superadmin.subscriptions.detail_title')" />

        <div class="py-10">
            <Link href="/superadmin/subscriptions" class="text-sm text-indigo-600 hover:underline dark:text-indigo-400">
                ← {{ t('superadmin.subscriptions.title') }}
            </Link>
            <h1 class="mt-4 text-2xl font-semibold">{{ t('superadmin.subscriptions.detail_title') }}</h1>

            <div class="mt-6 grid gap-6 md:grid-cols-2">
                <!-- Subscription -->
                <div class="rounded-lg border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
                    <dl class="space-y-2 text-sm">
                        <div class="flex justify-between">
                            <dt class="text-slate-500 dark:text-slate-400">{{ t('superadmin.subscriptions.tenant') }}</dt>
                            <dd>
                                <Link
                                    v-if="subscription.tenant.slug"
                                    :href="`/superadmin/tenants/${subscription.tenant.slug}`"
                                    class="text-indigo-600 hover:underline dark:text-indigo-400"
                                >
                                    {{ subscription.tenant.name }}
                                </Link>
                                <span v-else>{{ subscription.tenant.name ?? '—' }}</span>
                            </dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-slate-500 dark:text-slate-400">{{ t('superadmin.subscriptions.plan') }}</dt>
                            <dd>{{ subscription.plan?.name ?? '—' }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-slate-500 dark:text-slate-400">{{ t('fleet.fields.status') }}</dt>
                            <dd>{{ t(`superadmin.statuses.${subscription.status}`) }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-slate-500 dark:text-slate-400">{{ t('superadmin.subscriptions.billing_cycle') }}</dt>
                            <dd>{{ subscription.billing_cycle }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-slate-500 dark:text-slate-400">{{ t('superadmin.subscriptions.gateway') }}</dt>
                            <dd>{{ subscription.gateway ?? '—' }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-slate-500 dark:text-slate-400">{{ t('superadmin.subscriptions.gateway_id') }}</dt>
                            <dd>{{ subscription.gateway_subscription_id ?? '—' }}</dd>
                        </div>
                    </dl>
                </div>

                <!-- Period + pricing -->
                <div class="rounded-lg border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
                    <dl class="space-y-2 text-sm">
                        <div class="flex justify-between">
                            <dt class="text-slate-500 dark:text-slate-400">{{ t('superadmin.subscriptions.period_start') }}</dt>
                            <dd>{{ formatDate(subscription.current_period_start) }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-slate-500 dark:text-slate-400">{{ t('superadmin.subscriptions.period_end') }}</dt>
                            <dd>{{ formatDate(subscription.current_period_end) }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-slate-500 dark:text-slate-400">{{ t('superadmin.subscriptions.trial_ends') }}</dt>
                            <dd>{{ formatDate(subscription.trial_ends_at) }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-slate-500 dark:text-slate-400">{{ t('superadmin.subscriptions.cancelled_at') }}</dt>
                            <dd>{{ formatDate(subscription.cancelled_at) }}</dd>
                        </div>
                        <div v-if="subscription.plan" class="flex justify-between border-t border-slate-100 pt-2 dark:border-slate-800">
                            <dt class="text-slate-500 dark:text-slate-400">{{ t('superadmin.plans.monthly') }}</dt>
                            <dd>{{ formatAUD(subscription.plan.price_monthly) }}</dd>
                        </div>
                        <div v-if="subscription.plan" class="flex justify-between">
                            <dt class="text-slate-500 dark:text-slate-400">{{ t('superadmin.plans.annual') }}</dt>
                            <dd>{{ formatAUD(subscription.plan.price_annual) }}</dd>
                        </div>
                    </dl>
                </div>
            </div>

            <!-- Payments -->
            <h2 class="mt-10 text-lg font-semibold">{{ t('superadmin.subscriptions.payments') }}</h2>
            <p class="mt-4 text-sm text-slate-500 dark:text-slate-400">
                {{ t('superadmin.subscriptions.no_payments') }}
            </p>
        </div>
    </SuperAdminLayout>
</template>
