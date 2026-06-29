<script setup>
// Customer-portal agreement list. CustomerLayout. FUNCTIONAL ONLY.
import { computed } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import CustomerLayout from '@/Layouts/CustomerLayout.vue';

const { t } = useI18n();
const page = usePage();

defineProps({
    agreements: { type: Object, required: true },
});

const base = computed(() => `/portal/${page.props.tenant.slug}`);
</script>

<template>
    <CustomerLayout>
        <Head :title="t('customer.portal.agreements')" />

        <h1 class="mb-6 text-2xl font-semibold">{{ t('customer.portal.agreements') }}</h1>

        <p v-if="agreements.data.length === 0" class="text-sm text-slate-500 dark:text-slate-400">
            {{ t('customer.portal.no_agreements') }}
        </p>

        <div v-else class="overflow-hidden rounded-lg border border-slate-200 dark:border-slate-800">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-left text-slate-500 dark:bg-slate-900 dark:text-slate-400">
                    <tr>
                        <th class="px-4 py-2 font-medium">{{ t('customer.portal.agreement') }}</th>
                        <th class="px-4 py-2 font-medium">{{ t('customer.portal.type') }}</th>
                        <th class="px-4 py-2 font-medium">{{ t('customer.portal.vehicle') }}</th>
                        <th class="px-4 py-2 font-medium">{{ t('customer.portal.status') }}</th>
                        <th class="px-4 py-2 font-medium">{{ t('customer.portal.start_date') }}</th>
                        <th class="px-4 py-2"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 dark:divide-slate-800">
                    <tr v-for="agreement in agreements.data" :key="agreement.id">
                        <td class="px-4 py-3 font-medium">
                            {{ t('customer.portal.agreement_number', { id: agreement.id }) }}
                            <span class="text-xs text-slate-500 dark:text-slate-400">v{{ agreement.version }}</span>
                        </td>
                        <td class="px-4 py-3 capitalize">{{ agreement.type }}</td>
                        <td class="px-4 py-3 text-slate-500 dark:text-slate-400">
                            <span v-if="agreement.vehicle">{{ agreement.vehicle.make }} {{ agreement.vehicle.model }}</span>
                            <span v-else>—</span>
                        </td>
                        <td class="px-4 py-3 capitalize">{{ agreement.status }}</td>
                        <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ agreement.start_date ?? '—' }}</td>
                        <td class="px-4 py-3 text-right">
                            <Link
                                :href="`${base}/agreements/${agreement.id}`"
                                class="font-medium text-indigo-600 hover:underline dark:text-indigo-400"
                            >
                                {{ t('customer.portal.view') }}
                            </Link>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </CustomerLayout>
</template>
