<script setup>
// Super admin tenant detail — counts, subscription history, lifecycle actions.
import { Head, Link, router } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import SuperAdminLayout from '@/Layouts/SuperAdminLayout.vue';

const props = defineProps({
    tenant: { type: Object, required: true },
    counts: { type: Object, required: true },
    subscriptions: { type: Array, required: true },
});

const { t } = useI18n();

function suspend() {
    if (confirm(t('superadmin.tenants.confirm_suspend'))) {
        router.post(`/superadmin/tenants/${props.tenant.slug}/suspend`, {}, { preserveScroll: true });
    }
}

function activate() {
    router.post(`/superadmin/tenants/${props.tenant.slug}/activate`, {}, { preserveScroll: true });
}

function impersonate() {
    if (confirm(t('superadmin.tenants.confirm_impersonate'))) {
        router.post(`/superadmin/tenants/${props.tenant.slug}/impersonate`);
    }
}

function formatDate(value) {
    return value ? new Date(value).toLocaleDateString('en-AU') : '—';
}
</script>

<template>
    <SuperAdminLayout>
        <Head :title="tenant.name" />

        <div class="py-10">
            <Link href="/superadmin/tenants" class="text-sm text-indigo-600 hover:underline dark:text-indigo-400">
                ← {{ t('superadmin.tenants.title') }}
            </Link>

            <div class="mt-4 flex flex-wrap items-start justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-semibold">{{ tenant.name }}</h1>
                    <p class="text-sm text-slate-500 dark:text-slate-400">{{ tenant.slug }}</p>
                    <span class="mt-2 inline-block rounded-full bg-slate-100 px-2.5 py-0.5 text-xs dark:bg-slate-800">
                        {{ t(`superadmin.statuses.${tenant.status}`) }}
                    </span>
                </div>

                <div class="flex gap-3">
                    <button
                        v-if="tenant.status !== 'suspended'"
                        type="button"
                        class="rounded border border-red-300 px-3 py-1.5 text-sm text-red-600 hover:bg-red-50 dark:border-red-800 dark:hover:bg-red-950"
                        @click="suspend"
                    >
                        {{ t('superadmin.tenants.suspend') }}
                    </button>
                    <button
                        v-else
                        type="button"
                        class="rounded border border-green-300 px-3 py-1.5 text-sm text-green-600 hover:bg-green-50 dark:border-green-800 dark:hover:bg-green-950"
                        @click="activate"
                    >
                        {{ t('superadmin.tenants.activate') }}
                    </button>
                    <button
                        type="button"
                        class="rounded border border-slate-300 px-3 py-1.5 text-sm hover:bg-slate-100 dark:border-slate-700 dark:hover:bg-slate-800"
                        @click="impersonate"
                    >
                        {{ t('superadmin.tenants.impersonate') }}
                    </button>
                </div>
            </div>

            <!-- Counts -->
            <div class="mt-8 grid grid-cols-3 gap-4">
                <div class="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
                    <p class="text-sm text-slate-500 dark:text-slate-400">{{ t('superadmin.tenants.counts.users') }}</p>
                    <p class="mt-1 text-2xl font-semibold">{{ counts.users }}</p>
                </div>
                <div class="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
                    <p class="text-sm text-slate-500 dark:text-slate-400">{{ t('superadmin.tenants.counts.vehicles') }}</p>
                    <p class="mt-1 text-2xl font-semibold">{{ counts.vehicles }}</p>
                </div>
                <div class="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
                    <p class="text-sm text-slate-500 dark:text-slate-400">{{ t('superadmin.tenants.counts.invoices') }}</p>
                    <p class="mt-1 text-2xl font-semibold">{{ counts.invoices }}</p>
                </div>
            </div>

            <!-- Subscription history -->
            <h2 class="mt-10 text-lg font-semibold">{{ t('superadmin.tenants.subscription_history') }}</h2>
            <p v-if="!subscriptions.length" class="mt-4 text-sm text-slate-500 dark:text-slate-400">
                {{ t('superadmin.tenants.no_subscriptions') }}
            </p>
            <div v-else class="mt-4 overflow-hidden rounded border border-slate-200 dark:border-slate-800">
                <table class="w-full text-left text-sm">
                    <thead class="bg-slate-50 text-slate-500 dark:bg-slate-900 dark:text-slate-400">
                        <tr>
                            <th class="px-4 py-2 font-medium">{{ t('superadmin.tenants.plan') }}</th>
                            <th class="px-4 py-2 font-medium">{{ t('fleet.fields.status') }}</th>
                            <th class="px-4 py-2 font-medium">{{ t('superadmin.subscriptions.billing_cycle') }}</th>
                            <th class="px-4 py-2 font-medium">{{ t('superadmin.tenants.period') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="sub in subscriptions" :key="sub.id" class="border-t border-slate-100 dark:border-slate-800">
                            <td class="px-4 py-2">{{ sub.plan_name ?? '—' }}</td>
                            <td class="px-4 py-2">
                                <span class="rounded-full bg-slate-100 px-2.5 py-0.5 text-xs dark:bg-slate-800">
                                    {{ t(`superadmin.statuses.${sub.status}`) }}
                                </span>
                            </td>
                            <td class="px-4 py-2 text-slate-500 dark:text-slate-400">{{ sub.billing_cycle }}</td>
                            <td class="px-4 py-2 text-slate-500 dark:text-slate-400">
                                {{ formatDate(sub.current_period_start) }} – {{ formatDate(sub.current_period_end) }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </SuperAdminLayout>
</template>
