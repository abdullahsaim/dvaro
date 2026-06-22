<script setup>
// Tenant dashboard. FUNCTIONAL ONLY — design pass comes in a later session.
// Tenant name comes from the shared 'tenant' prop (TenantMiddleware); plan /
// subscription summary comes from TenantDashboardController.
import { computed } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    planName: { type: String, default: null },
    subscriptionStatus: { type: String, default: null },
    trialDaysRemaining: { type: Number, default: null },
});

const { t } = useI18n();
const page = usePage();

const user = computed(() => page.props.auth.user);
const tenant = computed(() => page.props.tenant);
const logoutUrl = computed(() => `/app/${tenant.value.slug}/logout`);

function logout() {
    router.post(logoutUrl.value);
}
</script>

<template>
    <AppLayout>
        <Head title="Dashboard" />

        <div class="py-10">
            <h1 class="text-2xl font-semibold">
                {{ t('dashboard.welcome', { name: user.name }) }}
            </h1>
            <p class="mt-1 text-slate-500 dark:text-slate-400">{{ tenant.name }}</p>

            <dl class="mt-6 grid max-w-md grid-cols-1 gap-4 sm:grid-cols-2">
                <div class="rounded border border-slate-200 p-4 dark:border-slate-800">
                    <dt class="text-sm text-slate-500 dark:text-slate-400">{{ t('dashboard.plan') }}</dt>
                    <dd class="mt-1 font-medium">{{ props.planName ?? t('dashboard.no_plan') }}</dd>
                </div>

                <div class="rounded border border-slate-200 p-4 dark:border-slate-800">
                    <dt class="text-sm text-slate-500 dark:text-slate-400">{{ t('dashboard.subscription_status') }}</dt>
                    <dd class="mt-1 font-medium capitalize">{{ props.subscriptionStatus ?? '—' }}</dd>
                </div>

                <div
                    v-if="props.trialDaysRemaining !== null"
                    class="rounded border border-slate-200 p-4 dark:border-slate-800"
                >
                    <dt class="text-sm text-slate-500 dark:text-slate-400">{{ t('dashboard.trial_days_remaining') }}</dt>
                    <dd class="mt-1 font-medium">{{ props.trialDaysRemaining }}</dd>
                </div>
            </dl>

            <button
                type="button"
                class="mt-6 rounded bg-slate-800 px-3 py-2 text-white dark:bg-slate-200 dark:text-slate-900"
                @click="logout"
            >
                {{ t('auth.logout') }}
            </button>
        </div>
    </AppLayout>
</template>
