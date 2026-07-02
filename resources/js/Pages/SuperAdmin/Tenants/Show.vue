<script setup>
// Super admin tenant detail — counts, subscription history, lifecycle actions.
// Design-system pass.
import { computed, ref } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import SuperAdminLayout from '@/Layouts/SuperAdminLayout.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import StatCard from '@/Components/UI/StatCard.vue';
import DataTable from '@/Components/UI/DataTable.vue';
import StatusBadge from '@/Components/UI/StatusBadge.vue';
import Button from '@/Components/UI/Button.vue';
import Modal from '@/Components/UI/Modal.vue';
import Select from '@/Components/UI/Select.vue';
import Input from '@/Components/UI/Input.vue';
import Textarea from '@/Components/UI/Textarea.vue';
import { useCurrency } from '@/composables/useCurrency.js';

const props = defineProps({
    tenant: { type: Object, required: true },
    counts: { type: Object, required: true },
    subscriptions: { type: Array, required: true },
    plans: { type: Array, default: () => [] },
    activePlanId: { type: Number, default: null },
});

const { t } = useI18n();
const { formatAUD, toCents } = useCurrency();

const statusVariants = {
    active: 'success',
    trialing: 'info',
    trial: 'info',
    suspended: 'danger',
    cancelled: 'neutral',
    past_due: 'warning',
};

// ── Assign plan ──────────────────────────────────────────────────────────────
const showAssign = ref(false);
const assignForm = useForm({ plan_id: '', billing_cycle: 'monthly' });

function openAssign() {
    assignForm.reset();
    assignForm.clearErrors();
    assignForm.plan_id = props.activePlanId ? String(props.activePlanId) : (props.plans[0] ? String(props.plans[0].id) : '');
    showAssign.value = true;
}

function submitAssign() {
    if (!confirm(t('superadmin.tenants.confirm_assign'))) return;
    assignForm.post(`/superadmin/tenants/${props.tenant.slug}/assign-plan`, {
        preserveScroll: true,
        onSuccess: () => {
            showAssign.value = false;
        },
    });
}

// ── Record offline payment ───────────────────────────────────────────────────
const methods = ['bank_transfer', 'cash', 'stripe', 'paypal', 'other'];
const showPayment = ref(false);
// amount entered in AUD dollars, transformed to cents on submit.
const paymentForm = useForm({ amount: '', method: 'bank_transfer', reference: '', notes: '', paid_at: '' });

function openPayment() {
    paymentForm.reset();
    paymentForm.clearErrors();
    paymentForm.paid_at = new Date().toISOString().slice(0, 10);
    showPayment.value = true;
}

function submitPayment() {
    paymentForm
        .transform((data) => ({ ...data, amount: toCents(data.amount) }))
        .post(`/superadmin/tenants/${props.tenant.slug}/offline-payment`, {
            preserveScroll: true,
            onSuccess: () => {
                showPayment.value = false;
            },
        });
}

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

// All recorded offline payments across every subscription, newest first.
const allPayments = computed(() =>
    props.subscriptions
        .flatMap((sub) => (sub.payments ?? []).map((p) => ({ ...p, plan_name: sub.plan_name })))
        .sort((a, b) => new Date(b.paid_at) - new Date(a.paid_at)),
);
</script>

<template>
    <SuperAdminLayout>
        <Head :title="tenant.name" />

        <Link href="/superadmin/tenants" class="text-sm font-medium text-ink-500 hover:underline">
            ← {{ t('superadmin.tenants.title') }}
        </Link>

        <PageHeader class="mt-4">
            <template #title>
                <span class="flex flex-wrap items-center gap-3">
                    {{ tenant.name }}
                    <StatusBadge :variant="statusVariants[tenant.status]" :label="t(`superadmin.statuses.${tenant.status}`)" />
                </span>
            </template>
            <template #description>{{ tenant.slug }}</template>
            <template #actions>
                <Button variant="primary" @click="openAssign">{{ t('superadmin.tenants.assign_plan') }}</Button>
                <Button variant="secondary" @click="openPayment">{{ t('superadmin.tenants.record_payment') }}</Button>
                <Button v-if="tenant.status !== 'suspended'" variant="danger" @click="suspend">{{ t('superadmin.tenants.suspend') }}</Button>
                <Button v-else variant="primary" @click="activate">{{ t('superadmin.tenants.activate') }}</Button>
                <Button variant="secondary" @click="impersonate">{{ t('superadmin.tenants.impersonate') }}</Button>
            </template>
        </PageHeader>

        <!-- Counts -->
        <div class="grid grid-cols-3 gap-4">
            <StatCard :label="t('superadmin.tenants.counts.users')" :value="counts.users" />
            <StatCard :label="t('superadmin.tenants.counts.vehicles')" :value="counts.vehicles" />
            <StatCard :label="t('superadmin.tenants.counts.invoices')" :value="counts.invoices" />
        </div>

        <!-- Subscription history -->
        <h2 class="mt-10 text-lg font-semibold text-ink-900 dark:text-ink-50">{{ t('superadmin.tenants.subscription_history') }}</h2>
        <div class="mt-4">
            <DataTable :columns="4" :empty="!subscriptions.length">
                <template #head>
                    <th class="px-4 py-2">{{ t('superadmin.tenants.plan') }}</th>
                    <th class="px-4 py-2">{{ t('fleet.fields.status') }}</th>
                    <th class="px-4 py-2">{{ t('superadmin.subscriptions.billing_cycle') }}</th>
                    <th class="px-4 py-2">{{ t('superadmin.tenants.period') }}</th>
                </template>
                <tr v-for="sub in subscriptions" :key="sub.id" class="text-ink-700 dark:text-ink-200">
                    <td class="px-4 py-2 text-ink-900 dark:text-ink-50">{{ sub.plan_name ?? '—' }}</td>
                    <td class="px-4 py-2">
                        <StatusBadge :variant="statusVariants[sub.status]" :label="t(`superadmin.statuses.${sub.status}`)" />
                    </td>
                    <td class="px-4 py-2 text-ink-500">{{ sub.billing_cycle }}</td>
                    <td class="px-4 py-2 text-ink-500">
                        {{ formatDate(sub.current_period_start) }} – {{ formatDate(sub.current_period_end) }}
                    </td>
                </tr>
                <template #empty>
                    <div class="px-4 py-6 text-center text-sm text-ink-400">{{ t('superadmin.tenants.no_subscriptions') }}</div>
                </template>
            </DataTable>
        </div>

        <!-- Offline payments -->
        <h2 class="mt-10 text-lg font-semibold text-ink-900 dark:text-ink-50">{{ t('superadmin.tenants.payments') }}</h2>
        <div class="mt-4">
            <DataTable :columns="5" :empty="!allPayments.length">
                <template #head>
                    <th class="px-4 py-2">{{ t('superadmin.tenants.paid_at') }}</th>
                    <th class="px-4 py-2">{{ t('superadmin.tenants.plan_label') }}</th>
                    <th class="px-4 py-2 text-right">{{ t('superadmin.tenants.amount_aud') }}</th>
                    <th class="px-4 py-2">{{ t('superadmin.tenants.method') }}</th>
                    <th class="px-4 py-2">{{ t('superadmin.tenants.reference') }}</th>
                </template>
                <tr v-for="p in allPayments" :key="p.id" class="text-ink-700 dark:text-ink-200">
                    <td class="px-4 py-2 text-ink-500">{{ formatDate(p.paid_at) }}</td>
                    <td class="px-4 py-2 text-ink-900 dark:text-ink-50">{{ p.plan_name ?? '—' }}</td>
                    <td class="px-4 py-2 text-right tabular-nums text-ink-900 dark:text-ink-50">{{ formatAUD(p.amount) }}</td>
                    <td class="px-4 py-2 text-ink-500">{{ t(`superadmin.tenants.methods.${p.method}`) }}</td>
                    <td class="px-4 py-2 text-ink-500">{{ p.reference ?? '—' }}</td>
                </tr>
                <template #empty>
                    <div class="px-4 py-6 text-center text-sm text-ink-400">{{ t('superadmin.tenants.no_payments') }}</div>
                </template>
            </DataTable>
        </div>

        <!-- Assign plan modal -->
        <Modal :show="showAssign" :title="t('superadmin.tenants.assign_plan_title')" @close="showAssign = false">
            <p class="mb-4 text-sm text-ink-500">{{ t('superadmin.tenants.assign_plan_hint') }}</p>
            <div class="space-y-4">
                <Select v-model="assignForm.plan_id" :label="t('superadmin.tenants.plan_label')" :error="assignForm.errors.plan_id" required>
                    <option value="" disabled>{{ t('superadmin.tenants.plan_label') }}</option>
                    <option v-for="plan in plans" :key="plan.id" :value="String(plan.id)">{{ plan.name }}</option>
                </Select>
                <Select v-model="assignForm.billing_cycle" :label="t('superadmin.tenants.billing_cycle')" :error="assignForm.errors.billing_cycle" required>
                    <option value="monthly">{{ t('superadmin.plans.monthly') }}</option>
                    <option value="annual">{{ t('superadmin.plans.annual') }}</option>
                </Select>
            </div>
            <template #footer>
                <Button variant="secondary" @click="showAssign = false">{{ t('billing.cancel') }}</Button>
                <Button variant="primary" :loading="assignForm.processing" @click="submitAssign">{{ t('superadmin.tenants.assign_plan') }}</Button>
            </template>
        </Modal>

        <!-- Record payment modal -->
        <Modal :show="showPayment" :title="t('superadmin.tenants.record_payment_title')" @close="showPayment = false">
            <p class="mb-4 text-sm text-ink-500">{{ t('superadmin.tenants.record_payment_hint') }}</p>
            <div class="space-y-4">
                <Input v-model="paymentForm.amount" type="number" step="0.01" min="0" :label="t('superadmin.tenants.amount_aud')" :error="paymentForm.errors.amount" required />
                <Select v-model="paymentForm.method" :label="t('superadmin.tenants.method')" :error="paymentForm.errors.method" required>
                    <option v-for="m in methods" :key="m" :value="m">{{ t(`superadmin.tenants.methods.${m}`) }}</option>
                </Select>
                <Input v-model="paymentForm.reference" :label="t('superadmin.tenants.reference')" :error="paymentForm.errors.reference" />
                <Input v-model="paymentForm.paid_at" type="date" :label="t('superadmin.tenants.paid_at')" :error="paymentForm.errors.paid_at" required />
                <Textarea v-model="paymentForm.notes" :label="t('superadmin.tenants.notes')" :error="paymentForm.errors.notes" rows="2" />
            </div>
            <template #footer>
                <Button variant="secondary" @click="showPayment = false">{{ t('billing.cancel') }}</Button>
                <Button variant="primary" :loading="paymentForm.processing" @click="submitPayment">{{ t('superadmin.tenants.record_payment') }}</Button>
            </template>
        </Modal>
    </SuperAdminLayout>
</template>
