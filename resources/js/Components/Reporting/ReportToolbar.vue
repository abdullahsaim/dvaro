<script setup>
// Shared reporting toolbar: optional date-range picker, PDF/Excel export
// buttons, and a list of recent exports with download links.
// FUNCTIONAL ONLY — design pass later. All strings via i18n reporting.*.
import { ref } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import Button from '@/Components/UI/Button.vue';
import Input from '@/Components/UI/Input.vue';

const props = defineProps({
    // One of: revenue | fleet | overdue | workshop (matches export types).
    reportType: { type: String, required: true },
    // { from, to } ISO date strings (omit for range-less reports like overdue).
    filters: { type: Object, default: null },
    // Recent ReportExport rows for this type (download list).
    exports: { type: Array, default: () => [] },
    // Whether to show the from/to date range controls.
    withDateRange: { type: Boolean, default: true },
});

const { t } = useI18n();
const page = usePage();
const slug = page.props.tenant.slug;

const from = ref(props.filters?.from ?? '');
const to = ref(props.filters?.to ?? '');

function applyRange() {
    router.get(
        `/app/${slug}/reports/${props.reportType}`,
        { from: from.value, to: to.value },
        { preserveScroll: true, preserveState: true },
    );
}

function exportFile(format) {
    router.post(
        `/app/${slug}/reports/export`,
        {
            report_type: props.reportType,
            format,
            from: from.value || null,
            to: to.value || null,
        },
        { preserveScroll: true, preserveState: true },
    );
}

function downloadUrl(id) {
    return `/app/${slug}/reports/exports/${id}`;
}
</script>

<template>
    <div class="mb-6 rounded-card border border-ink-200 bg-white p-4 shadow-subtle dark:border-ink-800 dark:bg-ink-900">
        <div class="flex flex-wrap items-end gap-4">
            <template v-if="withDateRange">
                <Input v-model="from" type="date" :label="t('reporting.date_from')" />
                <Input v-model="to" type="date" :label="t('reporting.date_to')" />
                <Button @click="applyRange">{{ t('reporting.apply') }}</Button>
            </template>

            <div class="ml-auto flex gap-2">
                <Button variant="secondary" @click="exportFile('pdf')">{{ t('reporting.export_pdf') }}</Button>
                <Button variant="secondary" @click="exportFile('excel')">{{ t('reporting.export_excel') }}</Button>
            </div>
        </div>

        <p v-if="withDateRange" class="mt-2 text-xs text-ink-400">{{ t('reporting.financial_year_hint') }}</p>

        <div class="mt-4">
            <p class="text-xs font-medium text-ink-500">{{ t('reporting.recent_exports') }}</p>
            <ul v-if="exports.length" class="mt-2 space-y-1 text-sm">
                <li v-for="ex in exports" :key="ex.id" class="flex items-center gap-3">
                    <span class="uppercase text-ink-400">{{ ex.format }}</span>
                    <a
                        v-if="ex.downloadable"
                        :href="downloadUrl(ex.id)"
                        class="font-medium text-ink-900 hover:underline dark:text-ink-100"
                    >
                        {{ t('reporting.download') }}
                    </a>
                    <span v-else class="text-ink-400">{{ t(`reporting.status.${ex.status}`) }}</span>
                    <span class="text-xs text-ink-400">{{ new Date(ex.created_at).toLocaleString() }}</span>
                </li>
            </ul>
            <p v-else class="mt-1 text-sm text-ink-400">{{ t('reporting.no_exports') }}</p>
        </div>
    </div>
</template>
