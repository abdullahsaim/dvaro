<script setup>
// Tenant dashboard. Design-system pass: PageHeader + StatCard KPI grid.
// Tenant name comes from the shared 'tenant' prop (TenantMiddleware); plan /
// subscription summary comes from TenantDashboardController.
import { computed } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import StatCard from '@/Components/UI/StatCard.vue';
import Button from '@/Components/UI/Button.vue';
import { useCurrency } from '@/composables/useCurrency';

const props = defineProps({
    planName: { type: String, default: null },
    subscriptionStatus: { type: String, default: null },
    trialDaysRemaining: { type: Number, default: null },
    summary: { type: Object, default: () => ({ outstanding_balance: 0, active_rentals: 0, vehicles_available: 0 }) },
});

const { t } = useI18n();
const { formatAUD } = useCurrency();
const page = usePage();

const user = computed(() => page.props.auth.user);
const tenant = computed(() => page.props.tenant);
const reportsUrl = computed(() => `/app/${tenant.value.slug}/reports`);
const mechanicsUrl = computed(() => `/app/${tenant.value.slug}/mechanics`);
</script>

<template>
    <AppLayout>
        <Head title="Dashboard" />

        <PageHeader
            :title="t('dashboard.welcome', { name: user.name })"
            :description="tenant.name"
        >
            <template #actions>
                <Button variant="secondary" @click="router.visit(reportsUrl)">
                    {{ t('dashboard.view_reports') }}
                </Button>
                <Button variant="secondary" @click="router.visit(mechanicsUrl)">
                    {{ t('dashboard.manage_mechanics') }}
                </Button>
            </template>
        </PageHeader>

        <!-- Operational KPIs -->
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
            <StatCard :label="t('dashboard.outstanding_balance')" :value="formatAUD(props.summary.outstanding_balance)" />
            <StatCard :label="t('dashboard.active_rentals')" :value="props.summary.active_rentals" />
            <StatCard :label="t('dashboard.vehicles_available')" :value="props.summary.vehicles_available" />
        </div>

        <!-- Subscription summary -->
        <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <StatCard :label="t('dashboard.plan')" :value="props.planName ?? t('dashboard.no_plan')" />
            <StatCard :label="t('dashboard.subscription_status')">
                <span class="capitalize">{{ props.subscriptionStatus ?? '—' }}</span>
            </StatCard>
            <StatCard
                v-if="props.trialDaysRemaining !== null"
                :label="t('dashboard.trial_days_remaining')"
                :value="props.trialDaysRemaining"
            />
        </div>
    </AppLayout>
</template>
