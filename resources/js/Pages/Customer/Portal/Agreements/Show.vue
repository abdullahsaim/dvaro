<script setup>
// Customer-portal agreement detail. CustomerLayout. Design-system pass.
import { computed } from 'vue';
import { Head, usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import CustomerLayout from '@/Layouts/CustomerLayout.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
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

        <PageHeader :title="t('customer.portal.agreement_number', { id: agreement.id })">
            <template #actions>
                <a
                    v-if="agreement.has_pdf"
                    :href="`${base}/agreements/${agreement.id}/pdf`"
                    class="text-sm font-medium text-ink-900 hover:underline dark:text-ink-100"
                >
                    {{ t('customer.portal.download_pdf') }}
                </a>
            </template>
        </PageHeader>

        <dl class="grid gap-4 rounded-card border border-ink-200 bg-white p-5 shadow-subtle sm:grid-cols-2 dark:border-ink-800 dark:bg-ink-900">
            <div v-for="row in rows" :key="row.label">
                <dt class="text-xs text-ink-500">{{ row.label }}</dt>
                <dd class="mt-0.5 font-medium capitalize text-ink-900 dark:text-ink-50">{{ row.value }}</dd>
            </div>
        </dl>

        <p v-if="!agreement.has_pdf" class="mt-4 text-sm text-ink-500">{{ t('customer.portal.no_pdf') }}</p>
    </CustomerLayout>
</template>
