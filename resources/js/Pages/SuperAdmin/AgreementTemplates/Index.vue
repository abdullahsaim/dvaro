<script setup>
// Super admin → Agreement templates: the PLATFORM DEFAULT terms every tenant
// inherits until they write or copy their own. Same editor as the tenant page.
import { Head } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import SuperAdminLayout from '@/Layouts/SuperAdminLayout.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import TemplateManager from '@/Components/Agreement/TemplateManager.vue';

defineProps({
    templates: { type: Array, required: true },
    types: { type: Array, required: true },
    states: { type: Array, required: true },
    mergeFields: { type: Object, required: true },
    sampleValues: { type: Object, required: true },
});

const { t } = useI18n();

const endpoints = {
    store: '/superadmin/agreement-templates',
    update: '/superadmin/agreement-templates/:id',
    copy: '',
};
</script>

<template>
    <SuperAdminLayout>
        <Head :title="t('agreement_template.platform_title')" />

        <PageHeader :title="t('agreement_template.platform_title')" :description="t('agreement_template.platform_intro')" />

        <div class="max-w-4xl">
            <TemplateManager
                :templates="templates"
                :types="types"
                :states="states"
                :merge-fields="mergeFields"
                :sample-values="sampleValues"
                :can-manage="true"
                :endpoints="endpoints"
            />
        </div>
    </SuperAdminLayout>
</template>
