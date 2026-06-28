<script setup>
// Super admin dashboard — platform KPIs + recent signups. FUNCTIONAL ONLY.
import { Head, Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import SuperAdminLayout from '@/Layouts/SuperAdminLayout.vue';
import { useCurrency } from '@/composables/useCurrency';

const props = defineProps({
    stats: { type: Object, required: true },
    recentTenants: { type: Array, required: true },
});

const { t } = useI18n();
const { formatAUD } = useCurrency();

const cards = [
    { key: 'total_tenants', label: 'superadmin.dashboard.total_tenants' },
    { key: 'active_tenants', label: 'superadmin.dashboard.active_tenants' },
    { key: 'trial_tenants', label: 'superadmin.dashboard.trial_tenants' },
    { key: 'new_this_month', label: 'superadmin.dashboard.new_this_month' },
];

function formatDate(value) {
    return value ? new Date(value).toLocaleDateString('en-AU') : '—';
}
</script>

<template>
    <SuperAdminLayout>
        <Head :title="t('superadmin.dashboard.title')" />

        <div class="py-10">
            <h1 class="text-2xl font-semibold">{{ t('superadmin.dashboard.title') }}</h1>

            <!-- KPI cards -->
            <div class="mt-6 grid grid-cols-2 gap-4 lg:grid-cols-5">
                <div
                    v-for="card in cards"
                    :key="card.key"
                    class="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900"
                >
                    <p class="text-sm text-slate-500 dark:text-slate-400">{{ t(card.label) }}</p>
                    <p class="mt-1 text-2xl font-semibold">{{ stats[card.key] }}</p>
                </div>

                <div class="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
                    <p class="text-sm text-slate-500 dark:text-slate-400">
                        {{ t('superadmin.dashboard.recurring_revenue') }}
                    </p>
                    <p class="mt-1 text-2xl font-semibold">{{ formatAUD(stats.recurring_revenue) }}</p>
                    <p class="mt-1 text-xs text-slate-400">{{ t('superadmin.dashboard.recurring_revenue_hint') }}</p>
                </div>
            </div>

            <!-- Recent signups -->
            <h2 class="mt-10 text-lg font-semibold">{{ t('superadmin.dashboard.recent_signups') }}</h2>
            <p v-if="!recentTenants.length" class="mt-4 text-sm text-slate-500 dark:text-slate-400">
                {{ t('superadmin.dashboard.no_tenants') }}
            </p>
            <div v-else class="mt-4 overflow-hidden rounded border border-slate-200 dark:border-slate-800">
                <table class="w-full text-left text-sm">
                    <thead class="bg-slate-50 text-slate-500 dark:bg-slate-900 dark:text-slate-400">
                        <tr>
                            <th class="px-4 py-2 font-medium">{{ t('superadmin.tenants.title') }}</th>
                            <th class="px-4 py-2 font-medium">{{ t('superadmin.tenants.plan') }}</th>
                            <th class="px-4 py-2 font-medium">{{ t('fleet.fields.status') }}</th>
                            <th class="px-4 py-2 font-medium">{{ t('superadmin.tenants.signed_up') }}</th>
                            <th class="px-4 py-2"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="tenant in recentTenants" :key="tenant.id" class="border-t border-slate-100 dark:border-slate-800">
                            <td class="px-4 py-2 font-medium">{{ tenant.name }}</td>
                            <td class="px-4 py-2 text-slate-500 dark:text-slate-400">{{ tenant.plan_name ?? '—' }}</td>
                            <td class="px-4 py-2">
                                <span class="rounded-full bg-slate-100 px-2.5 py-0.5 text-xs dark:bg-slate-800">
                                    {{ t(`superadmin.statuses.${tenant.status}`) }}
                                </span>
                            </td>
                            <td class="px-4 py-2 text-slate-500 dark:text-slate-400">{{ formatDate(tenant.created_at) }}</td>
                            <td class="px-4 py-2 text-right">
                                <Link
                                    :href="`/superadmin/tenants/${tenant.slug}`"
                                    class="text-indigo-600 hover:underline dark:text-indigo-400"
                                >
                                    {{ t('superadmin.tenants.view') }}
                                </Link>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </SuperAdminLayout>
</template>
