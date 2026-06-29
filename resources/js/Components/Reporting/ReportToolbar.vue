<script setup>
// Shared reporting toolbar: optional date-range picker, PDF/Excel export
// buttons, and a list of recent exports with download links.
// FUNCTIONAL ONLY — design pass later. All strings via i18n reporting.*.
import { ref } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';

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
    <div class="mb-6 rounded border border-slate-200 p-4 dark:border-slate-800">
        <div class="flex flex-wrap items-end gap-4">
            <template v-if="withDateRange">
                <div>
                    <label class="block text-xs text-slate-500 dark:text-slate-400">{{ t('reporting.date_from') }}</label>
                    <input
                        v-model="from"
                        type="date"
                        class="mt-1 rounded border border-slate-300 bg-transparent px-2 py-1 text-sm dark:border-slate-700"
                    >
                </div>
                <div>
                    <label class="block text-xs text-slate-500 dark:text-slate-400">{{ t('reporting.date_to') }}</label>
                    <input
                        v-model="to"
                        type="date"
                        class="mt-1 rounded border border-slate-300 bg-transparent px-2 py-1 text-sm dark:border-slate-700"
                    >
                </div>
                <button
                    type="button"
                    class="rounded bg-slate-800 px-3 py-1.5 text-sm text-white dark:bg-slate-200 dark:text-slate-900"
                    @click="applyRange"
                >
                    {{ t('reporting.apply') }}
                </button>
            </template>

            <div class="ml-auto flex gap-2">
                <button
                    type="button"
                    class="rounded border border-slate-300 px-3 py-1.5 text-sm dark:border-slate-700"
                    @click="exportFile('pdf')"
                >
                    {{ t('reporting.export_pdf') }}
                </button>
                <button
                    type="button"
                    class="rounded border border-slate-300 px-3 py-1.5 text-sm dark:border-slate-700"
                    @click="exportFile('excel')"
                >
                    {{ t('reporting.export_excel') }}
                </button>
            </div>
        </div>

        <p v-if="withDateRange" class="mt-2 text-xs text-slate-400">{{ t('reporting.financial_year_hint') }}</p>

        <div class="mt-4">
            <p class="text-xs font-medium text-slate-500 dark:text-slate-400">{{ t('reporting.recent_exports') }}</p>
            <ul v-if="exports.length" class="mt-2 space-y-1 text-sm">
                <li v-for="ex in exports" :key="ex.id" class="flex items-center gap-3">
                    <span class="uppercase text-slate-400">{{ ex.format }}</span>
                    <a
                        v-if="ex.downloadable"
                        :href="downloadUrl(ex.id)"
                        class="text-indigo-600 hover:underline dark:text-indigo-400"
                    >
                        {{ t('reporting.download') }}
                    </a>
                    <span v-else class="text-slate-400">{{ t(`reporting.status.${ex.status}`) }}</span>
                    <span class="text-xs text-slate-400">{{ new Date(ex.created_at).toLocaleString() }}</span>
                </li>
            </ul>
            <p v-else class="mt-1 text-sm text-slate-400">{{ t('reporting.no_exports') }}</p>
        </div>
    </div>
</template>
