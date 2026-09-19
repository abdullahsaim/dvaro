<script setup>
// Settings → Agreement templates (tenant). The company's own terms plus the
// read-only platform defaults, and the state pre-selected on new agreements.
// Everyone may read; only tenant_admin may write (enforced server-side).
import { computed } from 'vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import Button from '@/Components/UI/Button.vue';
import Select from '@/Components/UI/Select.vue';
import TemplateManager from '@/Components/Agreement/TemplateManager.vue';

const props = defineProps({
    templates: { type: Array, required: true },
    platformDefaults: { type: Array, default: () => [] },
    types: { type: Array, required: true },
    states: { type: Array, required: true },
    mergeFields: { type: Object, required: true },
    sampleValues: { type: Object, required: true },
    defaultState: { type: String, default: null },
    canManage: { type: Boolean, default: false },
});

const { t } = useI18n();
const page = usePage();
const base = computed(() => `/app/${page.props.tenant.slug}/agreements/templates`);

const endpoints = computed(() => ({
    store: base.value,
    update: `${base.value}/:id`,
    copy: `${base.value}/:id/copy`,
}));

const stateForm = useForm({ default_state: props.defaultState ?? '' });

function saveState() {
    stateForm.put(`${base.value}/default-state`, { preserveScroll: true });
}
</script>

<template>
    <AppLayout>
        <Head :title="t('agreement_template.title')" />

        <PageHeader :title="t('agreement_template.title')" :description="t('agreement_template.intro')">
            <template #actions>
                <Button variant="ghost" @click="router.visit(`/app/${page.props.tenant.slug}/agreements`)">
                    {{ t('common.back') }}
                </Button>
            </template>
        </PageHeader>

        <div class="max-w-4xl space-y-6">
            <!-- Default state -->
            <form
                class="flex flex-wrap items-end gap-3 rounded-card border border-ink-200 bg-white p-4 shadow-subtle dark:border-ink-800 dark:bg-ink-900"
                @submit.prevent="saveState"
            >
                <Select
                    v-model="stateForm.default_state"
                    class="w-48"
                    :label="t('agreement_template.default_state')"
                    :error="stateForm.errors.default_state"
                    :disabled="!canManage"
                >
                    <option value="">{{ t('agreement_template.no_default_state') }}</option>
                    <option v-for="state in states" :key="state" :value="state">{{ state }}</option>
                </Select>
                <Button v-if="canManage" type="submit" variant="secondary" :loading="stateForm.processing">
                    {{ t('common.save') }}
                </Button>
                <p class="text-sm text-ink-500">{{ t('agreement_template.default_state_hint') }}</p>
            </form>

            <p v-if="!canManage" class="rounded-card bg-ink-50 px-4 py-3 text-sm text-ink-500 dark:bg-ink-900">
                {{ t('agreement_template.admin_only') }}
            </p>

            <TemplateManager
                :templates="templates"
                :platform-defaults="platformDefaults"
                :types="types"
                :states="states"
                :merge-fields="mergeFields"
                :sample-values="sampleValues"
                :can-manage="canManage"
                :endpoints="endpoints"
            />
        </div>
    </AppLayout>
</template>
