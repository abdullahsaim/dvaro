<script setup>
// Platform-wide activity log — read-only oversight of every logged
// cross-tenant action. Design-system pass, mirrors Tenants/Index.vue's
// filter/table/pagination pattern.
import { ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import SuperAdminLayout from '@/Layouts/SuperAdminLayout.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import DataTable from '@/Components/UI/DataTable.vue';
import Input from '@/Components/UI/Input.vue';
import Select from '@/Components/UI/Select.vue';

const props = defineProps({
    logs: { type: Object, required: true },
    actions: { type: Array, required: true },
    filters: { type: Object, required: true },
});

const { t } = useI18n();
const searchTerm = ref(props.filters.search);
const actionFilter = ref(props.filters.action);
const expanded = ref(null);

function filter(params) {
    router.get('/superadmin/activity', {
        action: 'action' in params ? params.action : props.filters.action,
        search: 'search' in params ? params.search : searchTerm.value,
    }, { preserveState: true, replace: true });
}

function actionLabel(action) {
    return t(`superadmin.activity.actions.${action}`, action);
}

function formatDateTime(value) {
    if (!value) return '—';
    return new Date(value).toLocaleString('en-AU', {
        day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit',
    });
}

function toggle(id) {
    expanded.value = expanded.value === id ? null : id;
}

function hasDetails(log) {
    return (log.old_values && Object.keys(log.old_values).length) || (log.new_values && Object.keys(log.new_values).length);
}
</script>

<template>
    <SuperAdminLayout>
        <Head :title="t('superadmin.activity.title')" />

        <PageHeader :title="t('superadmin.activity.title')" :description="t('superadmin.activity.subtitle')" />

        <!-- Filters -->
        <div class="mb-4 flex flex-wrap items-center gap-3">
            <Select :model-value="actionFilter" class="w-56" @update:modelValue="(v) => { actionFilter = v; filter({ action: v }); }">
                <option value="">{{ t('superadmin.activity.all_actions') }}</option>
                <option v-for="a in actions" :key="a" :value="a">{{ actionLabel(a) }}</option>
            </Select>

            <form class="ml-auto" @submit.prevent="filter({ search: searchTerm })">
                <Input v-model="searchTerm" type="search" :placeholder="t('superadmin.activity.search_placeholder')" class="w-64" />
            </form>
        </div>

        <DataTable :columns="5" :empty="!logs.data.length" :pagination="logs">
            <template #head>
                <th class="px-4 py-2">{{ t('superadmin.activity.action') }}</th>
                <th class="px-4 py-2">{{ t('superadmin.activity.tenant') }}</th>
                <th class="px-4 py-2">{{ t('superadmin.activity.actor') }}</th>
                <th class="px-4 py-2">{{ t('superadmin.activity.when') }}</th>
                <th class="px-4 py-2 text-right">{{ t('superadmin.activity.details') }}</th>
            </template>
            <template v-for="log in logs.data" :key="log.id">
                <tr class="text-ink-700 dark:text-ink-200">
                    <td class="px-4 py-2">
                        <p class="font-medium text-ink-900 dark:text-ink-50">{{ actionLabel(log.action) }}</p>
                        <p v-if="log.subject_label" class="text-xs text-ink-400">{{ log.subject_label }}</p>
                    </td>
                    <td class="px-4 py-2 text-ink-500">
                        {{ log.tenant ? log.tenant.name : t('superadmin.activity.no_tenant') }}
                    </td>
                    <td class="px-4 py-2 text-ink-500">
                        {{ log.actor_label ?? t('superadmin.activity.system') }}
                    </td>
                    <td class="px-4 py-2 text-ink-500">{{ formatDateTime(log.created_at) }}</td>
                    <td class="px-4 py-2 text-right">
                        <button
                            v-if="hasDetails(log)"
                            type="button"
                            class="text-sm text-ink-500 hover:underline"
                            @click="toggle(log.id)"
                        >
                            {{ expanded === log.id ? '−' : '+' }}
                        </button>
                        <span v-else class="text-ink-300">—</span>
                    </td>
                </tr>
                <tr v-if="expanded === log.id" class="bg-ink-50 dark:bg-ink-950/40">
                    <td colspan="5" class="px-4 py-3">
                        <div class="grid grid-cols-1 gap-3 text-xs sm:grid-cols-2">
                            <div v-if="log.old_values">
                                <p class="font-semibold uppercase tracking-wide text-ink-400">Before</p>
                                <pre class="mt-1 whitespace-pre-wrap text-ink-600 dark:text-ink-300">{{ JSON.stringify(log.old_values, null, 2) }}</pre>
                            </div>
                            <div v-if="log.new_values">
                                <p class="font-semibold uppercase tracking-wide text-ink-400">After</p>
                                <pre class="mt-1 whitespace-pre-wrap text-ink-600 dark:text-ink-300">{{ JSON.stringify(log.new_values, null, 2) }}</pre>
                            </div>
                        </div>
                        <p v-if="log.ip" class="mt-2 text-xs text-ink-400">IP: {{ log.ip }}</p>
                    </td>
                </tr>
            </template>
            <template #empty>
                <div class="px-4 py-6 text-center text-sm text-ink-400">{{ t('superadmin.activity.empty') }}</div>
            </template>
        </DataTable>
    </SuperAdminLayout>
</template>
