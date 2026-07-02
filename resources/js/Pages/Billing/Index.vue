<script setup>
// Tenant billing portal — current plan, usage meters (vehicles/staff/customers),
// disabled modules, available plans, upgrade-request modal, billing history.
// Tenant-admin only (enforced server-side by BillingPolicy). Design-system UI.
import { computed, ref } from 'vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import StatusBadge from '@/Components/UI/StatusBadge.vue';
import Button from '@/Components/UI/Button.vue';
import Modal from '@/Components/UI/Modal.vue';
import Select from '@/Components/UI/Select.vue';
import Textarea from '@/Components/UI/Textarea.vue';
import DataTable from '@/Components/UI/DataTable.vue';
import { useCurrency } from '@/composables/useCurrency.js';

const props = defineProps({
    currentPlan: { type: Object, default: null },
    subscription: { type: Object, default: null },
    trialDaysRemaining: { type: Number, default: null },
    usage: { type: Object, required: true },
    disabledModules: { type: Array, default: () => [] },
    availablePlans: { type: Array, default: () => [] },
    history: { type: Array, default: () => [] },
});

const { t } = useI18n();
const { formatAUD } = useCurrency();

const slug = computed(() => usePage().props.tenant?.slug ?? '');

const statusVariants = {
    active: 'success',
    trialing: 'info',
    trial: 'info',
    suspended: 'danger',
    cancelled: 'neutral',
    past_due: 'warning',
};

// The three metered limits shown as progress bars.
const meters = computed(() => [
    { key: 'vehicles', label: t('billing.usage_vehicles'), ...props.usage.vehicles },
    { key: 'staff', label: t('billing.usage_staff'), ...props.usage.staff },
    { key: 'customers', label: t('billing.usage_customers'), ...props.usage.customers },
]);

function meterText(m) {
    // limit < 0 === unlimited
    return m.limit < 0
        ? `${m.current} · ${t('billing.usage_unlimited')}`
        : t('billing.usage_of', { current: m.current, limit: m.limit });
}

function barClass(m) {
    if (m.limit < 0) return 'bg-ink-400';
    if (m.approaching) return 'bg-danger-500';
    return 'bg-ink-800 dark:bg-ink-200';
}

function barWidth(m) {
    return `${m.limit < 0 ? 4 : Math.max(2, m.percentage)}%`;
}

function formatDate(value) {
    return value ? new Date(value).toLocaleDateString('en-AU') : '—';
}

function moduleLabel(key) {
    return t(`superadmin.plans.module_keys.${key}`);
}

// Upgrade request modal.
const showUpgrade = ref(false);
const form = useForm({ requested_plan_id: '', notes: '' });

function openUpgrade() {
    form.reset();
    form.clearErrors();
    // Preselect the first plan that isn't the current one.
    const firstOther = props.availablePlans.find((p) => !p.is_current);
    form.requested_plan_id = firstOther ? String(firstOther.id) : '';
    showUpgrade.value = true;
}

function submitUpgrade() {
    form.post(`/app/${slug.value}/billing/upgrade-request`, {
        preserveScroll: true,
        onSuccess: () => {
            showUpgrade.value = false;
        },
    });
}
</script>

<template>
    <AppLayout>
        <Head :title="t('billing.title')" />

        <PageHeader :title="t('billing.title')" :description="t('billing.subtitle')">
            <template #actions>
                <Button variant="primary" @click="openUpgrade">{{ t('billing.request_upgrade') }}</Button>
            </template>
        </PageHeader>

        <!-- Current plan -->
        <div class="rounded-card border border-ink-200 bg-white p-5 dark:border-ink-800 dark:bg-ink-900">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p class="text-2xs font-medium uppercase tracking-wide text-ink-400">{{ t('billing.current_plan') }}</p>
                    <p class="mt-1 text-xl font-semibold text-ink-900 dark:text-ink-50">
                        {{ currentPlan ? currentPlan.name : t('billing.no_plan') }}
                    </p>
                    <p v-if="currentPlan" class="mt-1 text-sm text-ink-500">
                        <span v-if="currentPlan.is_free">{{ t('billing.free') }}</span>
                        <span v-else-if="subscription && subscription.billing_cycle === 'annual'">
                            {{ formatAUD(currentPlan.price_annual) }}{{ t('billing.per_year') }}
                        </span>
                        <span v-else>{{ formatAUD(currentPlan.price_monthly) }}{{ t('billing.per_month') }}</span>
                    </p>
                </div>
                <div class="flex flex-col items-end gap-2">
                    <StatusBadge
                        v-if="subscription"
                        :variant="statusVariants[subscription.status] ?? 'neutral'"
                        :label="subscription.status"
                    />
                    <p v-if="trialDaysRemaining !== null" class="text-sm font-medium text-info-600 dark:text-info-500">
                        {{ t('billing.trial_days_remaining', { days: trialDaysRemaining }) }}
                    </p>
                    <p v-else-if="subscription && subscription.current_period_end" class="text-sm text-ink-500">
                        {{ t('billing.renews_on') }} {{ formatDate(subscription.current_period_end) }}
                    </p>
                </div>
            </div>
        </div>

        <!-- Usage meters -->
        <h2 class="mt-8 text-lg font-semibold text-ink-900 dark:text-ink-50">{{ t('billing.usage') }}</h2>
        <div class="mt-4 grid gap-4 sm:grid-cols-3">
            <div
                v-for="m in meters"
                :key="m.key"
                class="rounded-card border border-ink-200 bg-white p-4 dark:border-ink-800 dark:bg-ink-900"
            >
                <div class="flex items-baseline justify-between">
                    <p class="text-sm font-medium text-ink-700 dark:text-ink-300">{{ m.label }}</p>
                    <p class="text-sm tabular-nums text-ink-500">{{ meterText(m) }}</p>
                </div>
                <div class="mt-2 h-2 overflow-hidden rounded-full bg-ink-100 dark:bg-ink-800">
                    <div class="h-full rounded-full transition-all duration-500" :class="barClass(m)" :style="{ width: barWidth(m) }" />
                </div>
            </div>
        </div>

        <!-- Stored files (informational count only — no byte quota tracked) -->
        <div class="mt-4 rounded-card border border-ink-200 bg-white p-4 dark:border-ink-800 dark:bg-ink-900">
            <div class="flex items-baseline justify-between">
                <div>
                    <p class="text-sm font-medium text-ink-700 dark:text-ink-300">{{ t('billing.stored_files') }}</p>
                    <p class="text-xs text-ink-400">{{ t('billing.stored_files_hint') }}</p>
                </div>
                <p class="text-xl font-semibold tabular-nums text-ink-900 dark:text-ink-50">{{ usage.storage.files }}</p>
            </div>
        </div>

        <!-- Disabled modules -->
        <h2 class="mt-8 text-lg font-semibold text-ink-900 dark:text-ink-50">{{ t('billing.disabled_modules') }}</h2>
        <div class="mt-3">
            <p v-if="!disabledModules.length" class="text-sm text-ink-500">{{ t('billing.all_modules_included') }}</p>
            <div v-else class="flex flex-wrap gap-2">
                <span
                    v-for="key in disabledModules"
                    :key="key"
                    class="rounded-full border border-ink-200 px-3 py-1 text-xs text-ink-500 dark:border-ink-800"
                >
                    {{ moduleLabel(key) }}
                </span>
            </div>
        </div>

        <!-- Available plans -->
        <h2 class="mt-8 text-lg font-semibold text-ink-900 dark:text-ink-50">{{ t('billing.available_plans') }}</h2>
        <div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <div
                v-for="plan in availablePlans"
                :key="plan.id"
                class="flex flex-col rounded-card border bg-white p-5 dark:bg-ink-900"
                :class="plan.is_current ? 'border-ink-900 dark:border-ink-200' : 'border-ink-200 dark:border-ink-800'"
            >
                <div class="flex items-center justify-between">
                    <p class="font-semibold text-ink-900 dark:text-ink-50">{{ plan.name }}</p>
                    <StatusBadge v-if="plan.is_current" variant="success" :label="t('billing.current_badge')" />
                </div>
                <p class="mt-1 text-sm text-ink-500">
                    <span v-if="plan.is_free">{{ t('billing.free') }}</span>
                    <span v-else>{{ formatAUD(plan.price_monthly) }}{{ t('billing.per_month') }}</span>
                </p>
                <p v-if="plan.description" class="mt-2 text-sm text-ink-600 dark:text-ink-300">{{ plan.description }}</p>
                <div class="mt-3 flex flex-wrap gap-1.5">
                    <span
                        v-for="key in plan.modules"
                        :key="key"
                        class="rounded-full bg-ink-100 px-2 py-0.5 text-2xs text-ink-600 dark:bg-ink-800 dark:text-ink-300"
                    >
                        {{ moduleLabel(key) }}
                    </span>
                </div>
            </div>
        </div>

        <!-- Billing history -->
        <h2 class="mt-8 text-lg font-semibold text-ink-900 dark:text-ink-50">{{ t('billing.history') }}</h2>
        <div class="mt-4">
            <DataTable :columns="4" :empty="!history.length">
                <template #head>
                    <th class="px-4 py-2">{{ t('billing.plan') }}</th>
                    <th class="px-4 py-2">{{ t('billing.status') }}</th>
                    <th class="px-4 py-2">{{ t('billing.billing_cycle') }}</th>
                    <th class="px-4 py-2">{{ t('billing.period') }}</th>
                </template>
                <tr v-for="sub in history" :key="sub.id" class="text-ink-700 dark:text-ink-200">
                    <td class="px-4 py-2 text-ink-900 dark:text-ink-50">{{ sub.plan_name ?? '—' }}</td>
                    <td class="px-4 py-2">
                        <StatusBadge :variant="statusVariants[sub.status] ?? 'neutral'" :label="sub.status" />
                    </td>
                    <td class="px-4 py-2 text-ink-500">{{ sub.billing_cycle }}</td>
                    <td class="px-4 py-2 text-ink-500">
                        {{ formatDate(sub.current_period_start) }} – {{ formatDate(sub.current_period_end) }}
                    </td>
                </tr>
                <template #empty>
                    <div class="px-4 py-6 text-center text-sm text-ink-400">{{ t('billing.no_history') }}</div>
                </template>
            </DataTable>
        </div>

        <!-- Upgrade-request modal -->
        <Modal :show="showUpgrade" :title="t('billing.request_upgrade_title')" @close="showUpgrade = false">
            <p class="mb-4 text-sm text-ink-500">{{ t('billing.request_upgrade_hint') }}</p>
            <div class="space-y-4">
                <Select
                    v-model="form.requested_plan_id"
                    :label="t('billing.select_plan')"
                    :error="form.errors.requested_plan_id"
                    required
                >
                    <option value="" disabled>{{ t('billing.select_plan') }}</option>
                    <option v-for="plan in availablePlans" :key="plan.id" :value="String(plan.id)" :disabled="plan.is_current">
                        {{ plan.name }}<span v-if="plan.is_current"> — {{ t('billing.current_badge') }}</span>
                    </option>
                </Select>
                <Textarea
                    v-model="form.notes"
                    :label="t('billing.notes_optional')"
                    :error="form.errors.notes"
                    rows="3"
                />
            </div>
            <template #footer>
                <Button variant="secondary" @click="showUpgrade = false">{{ t('billing.cancel') }}</Button>
                <Button variant="primary" :loading="form.processing" @click="submitUpgrade">
                    {{ t('billing.submit_request') }}
                </Button>
            </template>
        </Modal>
    </AppLayout>
</template>
