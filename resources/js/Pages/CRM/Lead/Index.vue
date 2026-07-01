<script setup>
// Lead list — design-system pass. Status filter tabs (with counts) + name/phone
// search, paginated DataTable.
import { computed, ref } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import DataTable from '@/Components/UI/DataTable.vue';
import StatusBadge from '@/Components/UI/StatusBadge.vue';
import EmptyState from '@/Components/UI/EmptyState.vue';
import Button from '@/Components/UI/Button.vue';
import Input from '@/Components/UI/Input.vue';
import { UserPlusIcon, CheckIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    leads: { type: Object, required: true }, // Laravel paginator payload
    filters: { type: Object, required: true }, // { status, search }
    counts: { type: Object, required: true }, // { all, new, contacted, ... }
});

const { t } = useI18n();
const page = usePage();

const slug = computed(() => page.props.tenant.slug);
const base = computed(() => `/app/${slug.value}/leads`);

const search = ref(props.filters.search ?? '');

// '' = all; the rest map 1:1 to ?status= values.
const tabs = ['', 'new', 'contacted', 'converted', 'expired', 'rejected'];
const activeTab = computed(() => props.filters.status ?? '');

// lead status enum → generic StatusBadge variant.
const statusVariants = {
    new: 'info',
    contacted: 'neutral',
    converted: 'success',
    expired: 'neutral',
    rejected: 'danger',
};

function query(overrides = {}) {
    const params = {
        search: search.value || undefined,
        status: props.filters.status || undefined,
        ...overrides,
    };
    router.get(base.value, params, { preserveState: true, preserveScroll: true, replace: true });
}

function submitSearch() {
    query({ search: search.value || undefined });
}

function filterBy(key) {
    query({ status: key || undefined });
}

function convert(lead) {
    router.post(`${base.value}/${lead.id}/convert`, {}, { preserveScroll: true });
}

function expire(lead) {
    if (!window.confirm(t('crm.confirm_expire'))) return;
    router.post(`${base.value}/${lead.id}/expire`, {}, { preserveScroll: true });
}

function destroy(lead) {
    if (!window.confirm(t('crm.confirm_delete'))) return;
    router.delete(`${base.value}/${lead.id}`, { preserveScroll: true });
}

// Convertible mirrors Lead::isConvertible(): submitted, not converted/expired.
function isConvertible(lead) {
    return ['new', 'contacted'].includes(lead.status) && !!lead.submitted_at && !lead.expires_manually;
}
</script>

<template>
    <AppLayout>
        <Head :title="t('crm.title')" />

        <PageHeader :title="t('crm.title')">
            <template #actions>
                <Button @click="router.visit(`${base}/create`)">{{ t('crm.add_lead') }}</Button>
            </template>
        </PageHeader>

        <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
            <!-- Status filter tabs with counts -->
            <div class="flex flex-wrap gap-2">
                <button
                    v-for="tab in tabs"
                    :key="tab"
                    type="button"
                    class="rounded-full px-3 py-1 text-sm transition-colors"
                    :class="activeTab === tab
                        ? 'bg-ink-950 text-white dark:bg-ink-50 dark:text-ink-950'
                        : 'bg-ink-100 text-ink-600 hover:bg-ink-200 dark:bg-ink-800 dark:text-ink-300 dark:hover:bg-ink-700'"
                    @click="filterBy(tab)"
                >
                    {{ tab === '' ? t('common.all') : t(`crm.statuses.${tab}`) }}
                    <span class="ml-1 opacity-60">{{ tab === '' ? counts.all : counts[tab] }}</span>
                </button>
            </div>

            <!-- Search -->
            <form class="flex items-end gap-2" @submit.prevent="submitSearch">
                <Input v-model="search" type="search" :placeholder="t('crm.search_placeholder')" class="w-64" />
                <Button type="submit" variant="secondary">{{ t('common.search') }}</Button>
            </form>
        </div>

        <DataTable :columns="6" :empty="leads.data.length === 0" :pagination="leads">
            <template #head>
                <th class="px-4 py-3">{{ t('crm.fields.name') }}</th>
                <th class="px-4 py-3">{{ t('crm.fields.email') }}</th>
                <th class="px-4 py-3">{{ t('crm.fields.phone') }}</th>
                <th class="px-4 py-3">{{ t('crm.status_label') }}</th>
                <th class="px-4 py-3">{{ t('crm.submitted') }}</th>
                <th class="px-4 py-3 text-right">{{ t('common.actions') }}</th>
            </template>

            <tr v-for="lead in leads.data" :key="lead.id" class="text-ink-700 dark:text-ink-200">
                <td class="px-4 py-3 font-medium text-ink-900 dark:text-ink-50">{{ lead.name }}</td>
                <td class="px-4 py-3">{{ lead.email }}</td>
                <td class="px-4 py-3">{{ lead.phone }}</td>
                <td class="px-4 py-3">
                    <StatusBadge :variant="statusVariants[lead.status]" :label="t(`crm.statuses.${lead.status}`)" />
                </td>
                <td class="px-4 py-3">
                    <CheckIcon v-if="lead.submitted_at" class="h-4 w-4 text-success-600 dark:text-success-500" />
                    <span v-else class="text-ink-400">{{ t('common.none') }}</span>
                </td>
                <td class="px-4 py-3">
                    <div class="flex justify-end gap-3">
                        <Link :href="`${base}/${lead.id}`" class="text-sm font-medium text-ink-900 hover:underline dark:text-ink-100">
                            {{ t('crm.lead_details') }}
                        </Link>
                        <button v-if="isConvertible(lead)" type="button" class="text-sm text-success-600 hover:underline dark:text-success-500" @click="convert(lead)">
                            {{ t('crm.convert') }}
                        </button>
                        <button v-if="!lead.expires_manually && lead.status !== 'converted'" type="button" class="text-sm text-warning-600 hover:underline dark:text-warning-500" @click="expire(lead)">
                            {{ t('crm.expire') }}
                        </button>
                        <button type="button" class="text-sm text-danger-600 hover:underline dark:text-danger-500" @click="destroy(lead)">
                            {{ t('common.delete') }}
                        </button>
                    </div>
                </td>
            </tr>

            <template #empty>
                <EmptyState :title="t('crm.empty')" :message="t('crm.add_lead')">
                    <template #icon><UserPlusIcon class="h-6 w-6" /></template>
                    <template #action>
                        <Button @click="router.visit(`${base}/create`)">{{ t('crm.add_lead') }}</Button>
                    </template>
                </EmptyState>
            </template>
        </DataTable>
    </AppLayout>
</template>
