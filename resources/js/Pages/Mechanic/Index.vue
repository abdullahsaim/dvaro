<script setup>
// Mechanic accounts list (tenant admin). Design-system pass.
import { computed } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import DataTable from '@/Components/UI/DataTable.vue';
import StatusBadge from '@/Components/UI/StatusBadge.vue';
import EmptyState from '@/Components/UI/EmptyState.vue';
import Button from '@/Components/UI/Button.vue';
import { IdentificationIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    mechanics: { type: Object, required: true }, // Laravel paginator payload
});

const { t } = useI18n();
const page = usePage();

const slug = computed(() => page.props.tenant.slug);
const base = computed(() => `/app/${slug.value}/mechanics`);

function destroy(mechanic) {
    if (!window.confirm(t('mechanic.confirm_delete'))) return;
    router.delete(`${base.value}/${mechanic.id}`, { preserveScroll: true });
}
</script>

<template>
    <AppLayout>
        <Head :title="t('mechanic.title')" />

        <PageHeader :title="t('mechanic.title')">
            <template #actions>
                <Button @click="router.visit(`${base}/create`)">{{ t('mechanic.add_mechanic') }}</Button>
            </template>
        </PageHeader>

        <DataTable :columns="5" :empty="mechanics.data.length === 0" :pagination="mechanics">
            <template #head>
                <th class="px-4 py-3">{{ t('mechanic.fields.name') }}</th>
                <th class="px-4 py-3">{{ t('mechanic.fields.email') }}</th>
                <th class="px-4 py-3">{{ t('mechanic.fields.phone') }}</th>
                <th class="px-4 py-3">{{ t('mechanic.fields.is_active') }}</th>
                <th class="px-4 py-3 text-right">{{ t('common.actions') }}</th>
            </template>
            <tr v-for="mechanic in mechanics.data" :key="mechanic.id" class="text-ink-700 dark:text-ink-200">
                <td class="px-4 py-3 font-medium text-ink-900 dark:text-ink-50">{{ mechanic.name }}</td>
                <td class="px-4 py-3">{{ mechanic.email }}</td>
                <td class="px-4 py-3">{{ mechanic.phone ?? '—' }}</td>
                <td class="px-4 py-3">
                    <StatusBadge
                        :variant="mechanic.is_active ? 'success' : 'neutral'"
                        :label="mechanic.is_active ? t('mechanic.active_badge') : t('mechanic.inactive_badge')"
                    />
                </td>
                <td class="px-4 py-3">
                    <div class="flex justify-end gap-3">
                        <Link :href="`${base}/${mechanic.id}/edit`" class="text-sm text-ink-500 hover:underline">
                            {{ t('common.edit') }}
                        </Link>
                        <button type="button" class="text-sm text-danger-600 hover:underline dark:text-danger-500" @click="destroy(mechanic)">
                            {{ t('common.delete') }}
                        </button>
                    </div>
                </td>
            </tr>
            <template #empty>
                <EmptyState :title="t('mechanic.empty')" :message="t('mechanic.add_mechanic')">
                    <template #icon><IdentificationIcon class="h-6 w-6" /></template>
                    <template #action>
                        <Button @click="router.visit(`${base}/create`)">{{ t('mechanic.add_mechanic') }}</Button>
                    </template>
                </EmptyState>
            </template>
        </DataTable>
    </AppLayout>
</template>
