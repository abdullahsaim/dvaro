<script setup>
// Customer-portal agreement detail. CustomerLayout. FUNCTIONAL ONLY.
import { computed } from 'vue';
import { Head, usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import CustomerLayout from '@/Layouts/CustomerLayout.vue';
import { useCurrency } from '@/composables/useCurrency';

const { t } = useI18n();
const { formatAUD } = useCurrency();
const page = usePage();

const props = defineProps({
    agreement: { type: Object, required: true },
});

const base = computed(() => `/portal/${page.props.tenant.slug}`);

const rows = computed(() => [
    { label: t('customer.portal.type'), value: props.agreement.type },
    { label: t('customer.portal.status'), value: props.agreement.status },
    { label: t('customer.portal.version'), value: `v${props.agreement.version}` },
    { label: t('customer.portal.rate'), value: formatAUD(props.agreement.rate) },
    { label: t('customer.portal.bond'), value: formatAUD(props.agreement.bond_amount) },
    {
        label: t('customer.portal.vehicle'),
        value: props.agreement.vehicle
            ? `${props.agreement.vehicle.make} ${props.agreement.vehicle.model} · ${props.agreement.vehicle.registration_number}`
            : '—',
    },
    { label: t('customer.portal.start_date'), value: props.agreement.start_date ?? '—' },
    { label: t('customer.portal.end_date'), value: props.agreement.end_date ?? '—' },
    { label: t('customer.portal.signed_at'), value: props.agreement.signed_at ?? '—' },
]);
</script>

<template>
    <CustomerLayout>
        <Head :title="t('customer.portal.agreement_number', { id: agreement.id })" />

        <div class="mb-6 flex items-center justify-between">
            <h1 class="text-2xl font-semibold">{{ t('customer.portal.agreement_number', { id: agreement.id }) }}</h1>
            <a
                v-if="agreement.has_pdf"
                :href="`${base}/agreements/${agreement.id}/pdf`"
                class="text-sm text-indigo-600 hover:underline dark:text-indigo-400"
            >
                {{ t('customer.portal.download_pdf') }}
            </a>
        </div>

        <dl class="grid gap-4 rounded-lg border border-slate-200 p-5 sm:grid-cols-2 dark:border-slate-800">
            <div v-for="row in rows" :key="row.label">
                <dt class="text-xs text-slate-500 dark:text-slate-400">{{ row.label }}</dt>
                <dd class="mt-0.5 font-medium capitalize">{{ row.value }}</dd>
            </div>
        </dl>

        <p v-if="!agreement.has_pdf" class="mt-4 text-sm text-slate-500 dark:text-slate-400">
            {{ t('customer.portal.no_pdf') }}
        </p>
    </CustomerLayout>
</template>
