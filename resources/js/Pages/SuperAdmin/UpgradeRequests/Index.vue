<script setup>
// Super admin upgrade-request queue — pending tenant plan-upgrade requests.
// Mark contacted, or complete (assigns the requested plan on a chosen cycle).
import { ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import SuperAdminLayout from '@/Layouts/SuperAdminLayout.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import DataTable from '@/Components/UI/DataTable.vue';
import Button from '@/Components/UI/Button.vue';
import Modal from '@/Components/UI/Modal.vue';
import Select from '@/Components/UI/Select.vue';

const props = defineProps({
    requests: { type: Object, required: true },
});

const { t } = useI18n();

function formatDate(value) {
    return value ? new Date(value).toLocaleDateString('en-AU') : '—';
}

function markContacted(id) {
    router.post(`/superadmin/upgrade-requests/${id}/contacted`, {}, { preserveScroll: true });
}

// Complete modal — pick a billing cycle before assigning.
const showComplete = ref(false);
const activeId = ref(null);
const billingCycle = ref('monthly');
const processing = ref(false);

function openComplete(id) {
    activeId.value = id;
    billingCycle.value = 'monthly';
    showComplete.value = true;
}

function submitComplete() {
    processing.value = true;
    router.post(
        `/superadmin/upgrade-requests/${activeId.value}/complete`,
        { billing_cycle: billingCycle.value },
        {
            preserveScroll: true,
            onFinish: () => {
                processing.value = false;
                showComplete.value = false;
            },
        },
    );
}
</script>

<template>
    <SuperAdminLayout>
        <Head :title="t('superadmin.upgrade_requests.title')" />

        <PageHeader :title="t('superadmin.upgrade_requests.title')" :description="t('superadmin.upgrade_requests.subtitle')" />

        <DataTable :columns="6" :empty="!requests.data.length" :pagination="requests">
            <template #head>
                <th class="px-4 py-2">{{ t('superadmin.upgrade_requests.tenant') }}</th>
                <th class="px-4 py-2">{{ t('superadmin.upgrade_requests.current_plan') }}</th>
                <th class="px-4 py-2">{{ t('superadmin.upgrade_requests.requested_plan') }}</th>
                <th class="px-4 py-2">{{ t('superadmin.upgrade_requests.notes') }}</th>
                <th class="px-4 py-2">{{ t('superadmin.upgrade_requests.requested_at') }}</th>
                <th class="px-4 py-2 text-right">{{ t('superadmin.tenants.billing_actions') }}</th>
            </template>
            <tr v-for="req in requests.data" :key="req.id" class="text-ink-700 dark:text-ink-200">
                <td class="px-4 py-2 text-ink-900 dark:text-ink-50">{{ req.tenant_name ?? '—' }}</td>
                <td class="px-4 py-2 text-ink-500">{{ req.current_plan ?? '—' }}</td>
                <td class="px-4 py-2 text-ink-900 dark:text-ink-50">{{ req.requested_plan ?? '—' }}</td>
                <td class="px-4 py-2 max-w-xs truncate text-ink-500" :title="req.notes">{{ req.notes ?? '—' }}</td>
                <td class="px-4 py-2 text-ink-500">{{ formatDate(req.created_at) }}</td>
                <td class="px-4 py-2">
                    <div class="flex justify-end gap-2">
                        <Button variant="secondary" size="sm" @click="markContacted(req.id)">
                            {{ t('superadmin.upgrade_requests.mark_contacted') }}
                        </Button>
                        <Button variant="primary" size="sm" @click="openComplete(req.id)">
                            {{ t('superadmin.upgrade_requests.complete') }}
                        </Button>
                    </div>
                </td>
            </tr>
            <template #empty>
                <div class="px-4 py-6 text-center text-sm text-ink-400">
                    {{ t('superadmin.upgrade_requests.empty') }}
                </div>
            </template>
        </DataTable>

        <!-- Complete modal -->
        <Modal :show="showComplete" :title="t('superadmin.upgrade_requests.complete')" @close="showComplete = false">
            <p class="mb-4 text-sm text-ink-500">{{ t('superadmin.upgrade_requests.confirm_complete') }}</p>
            <Select v-model="billingCycle" :label="t('superadmin.upgrade_requests.billing_cycle')" required>
                <option value="monthly">{{ t('superadmin.plans.monthly') }}</option>
                <option value="annual">{{ t('superadmin.plans.annual') }}</option>
            </Select>
            <template #footer>
                <Button variant="secondary" @click="showComplete = false">{{ t('billing.cancel') }}</Button>
                <Button variant="primary" :loading="processing" @click="submitComplete">
                    {{ t('superadmin.upgrade_requests.complete') }}
                </Button>
            </template>
        </Modal>
    </SuperAdminLayout>
</template>
