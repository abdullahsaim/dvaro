<script setup>
// Platform-wide subscriptions — status + plan filters. Read-only. FUNCTIONAL.
import { Head, Link, router } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import SuperAdminLayout from '@/Layouts/SuperAdminLayout.vue';

const props = defineProps({
    subscriptions: { type: Object, required: true },
    statuses: { type: Array, required: true },
    activeStatus: { type: String, default: null },
    plans: { type: Array, required: true },
    activePlanId: { type: Number, default: null },
});

const { t } = useI18n();

function filter(params) {
    router.get('/superadmin/subscriptions', {
        status: 'status' in params ? params.status : props.activeStatus,
        plan_id: 'plan_id' in params ? params.plan_id : props.activePlanId,
    }, { preserveState: true, replace: true });
}

function onPlanChange(e) {
    const v = e.target.value;
    filter({ plan_id: v === '' ? null : Number(v) });
}

function formatDate(value) {
    return value ? new Date(value).toLocaleDateString('en-AU') : '—';
}
</script>

<template>
    <SuperAdminLayout>
        <Head :title="t('superadmin.subscriptions.title')" />

        <div class="py-10">
            <h1 class="text-2xl font-semibold">{{ t('superadmin.subscriptions.title') }}</h1>

            <!-- Filters -->
            <div class="mt-6 flex flex-wrap items-center gap-4">
                <div class="flex flex-wrap gap-2">
                    <button
                        type="button"
                        class="rounded-full px-3 py-1 text-sm"
                        :class="activeStatus === null ? 'bg-slate-800 text-white dark:bg-slate-200 dark:text-slate-900' : 'bg-slate-100 dark:bg-slate-800'"
                        @click="filter({ status: null })"
                    >
                        {{ t('common.all') }}
                    </button>
                    <button
                        v-for="s in statuses"
                        :key="s"
                        type="button"
                        class="rounded-full px-3 py-1 text-sm"
                        :class="activeStatus === s ? 'bg-slate-800 text-white dark:bg-slate-200 dark:text-slate-900' : 'bg-slate-100 dark:bg-slate-800'"
                        @click="filter({ status: s })"
                    >
                        {{ t(`superadmin.statuses.${s}`) }}
                    </button>
                </div>

                <label class="ml-auto block">
                    <span class="sr-only">{{ t('superadmin.subscriptions.plan') }}</span>
                    <select
                        class="rounded border border-slate-300 px-3 py-1.5 text-sm dark:border-slate-700 dark:bg-slate-900"
                        :value="activePlanId ?? ''"
                        @change="onPlanChange"
                    >
                        <option value="">{{ t('superadmin.subscriptions.all_plans') }}</option>
                        <option v-for="p in plans" :key="p.id" :value="p.id">{{ p.name }}</option>
                    </select>
                </label>
            </div>

            <!-- Table -->
            <p v-if="!subscriptions.data.length" class="mt-8 text-sm text-slate-500 dark:text-slate-400">
                {{ t('superadmin.subscriptions.empty') }}
            </p>
            <div v-else class="mt-6 overflow-hidden rounded border border-slate-200 dark:border-slate-800">
                <table class="w-full text-left text-sm">
                    <thead class="bg-slate-50 text-slate-500 dark:bg-slate-900 dark:text-slate-400">
                        <tr>
                            <th class="px-4 py-2 font-medium">{{ t('superadmin.subscriptions.tenant') }}</th>
                            <th class="px-4 py-2 font-medium">{{ t('superadmin.subscriptions.plan') }}</th>
                            <th class="px-4 py-2 font-medium">{{ t('fleet.fields.status') }}</th>
                            <th class="px-4 py-2 font-medium">{{ t('superadmin.subscriptions.billing_cycle') }}</th>
                            <th class="px-4 py-2 font-medium">{{ t('superadmin.subscriptions.period_end') }}</th>
                            <th class="px-4 py-2"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="sub in subscriptions.data" :key="sub.id" class="border-t border-slate-100 dark:border-slate-800">
                            <td class="px-4 py-2 font-medium">{{ sub.tenant_name ?? '—' }}</td>
                            <td class="px-4 py-2 text-slate-500 dark:text-slate-400">{{ sub.plan_name ?? '—' }}</td>
                            <td class="px-4 py-2">
                                <span class="rounded-full bg-slate-100 px-2.5 py-0.5 text-xs dark:bg-slate-800">
                                    {{ t(`superadmin.statuses.${sub.status}`) }}
                                </span>
                            </td>
                            <td class="px-4 py-2 text-slate-500 dark:text-slate-400">{{ sub.billing_cycle }}</td>
                            <td class="px-4 py-2 text-slate-500 dark:text-slate-400">{{ formatDate(sub.current_period_end) }}</td>
                            <td class="px-4 py-2 text-right">
                                <Link :href="`/superadmin/subscriptions/${sub.id}`" class="text-indigo-600 hover:underline dark:text-indigo-400">
                                    {{ t('superadmin.subscriptions.view') }}
                                </Link>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div v-if="subscriptions.links" class="mt-4 flex flex-wrap gap-1">
                <component
                    :is="link.url ? 'button' : 'span'"
                    v-for="(link, i) in subscriptions.links"
                    :key="i"
                    type="button"
                    class="rounded px-3 py-1 text-sm"
                    :class="link.active ? 'bg-slate-800 text-white dark:bg-slate-200 dark:text-slate-900' : 'text-slate-500 dark:text-slate-400'"
                    :disabled="!link.url"
                    @click="link.url && router.get(link.url, {}, { preserveState: true })"
                    v-html="link.label"
                />
            </div>
        </div>
    </SuperAdminLayout>
</template>
