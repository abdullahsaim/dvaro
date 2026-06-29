<script setup>
// Sub-navigation across the reporting pages. FUNCTIONAL ONLY.
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();
const page = usePage();
const slug = page.props.tenant.slug;

const links = computed(() => [
    { key: 'dashboard', href: `/app/${slug}/reports`, label: t('reporting.nav.dashboard') },
    { key: 'revenue', href: `/app/${slug}/reports/revenue`, label: t('reporting.nav.revenue') },
    { key: 'fleet', href: `/app/${slug}/reports/fleet`, label: t('reporting.nav.fleet') },
    { key: 'overdue', href: `/app/${slug}/reports/overdue`, label: t('reporting.nav.overdue') },
    { key: 'workshop', href: `/app/${slug}/reports/workshop`, label: t('reporting.nav.workshop') },
    { key: 'customers', href: `/app/${slug}/reports/customers`, label: t('reporting.nav.customers') },
    { key: 'maintenance', href: `/app/${slug}/reports/maintenance`, label: t('reporting.nav.maintenance') },
]);

defineProps({ active: { type: String, required: true } });
</script>

<template>
    <nav class="mb-6 flex flex-wrap gap-2 border-b border-slate-200 pb-3 dark:border-slate-800">
        <Link
            v-for="link in links"
            :key="link.key"
            :href="link.href"
            class="rounded px-3 py-1.5 text-sm"
            :class="active === link.key
                ? 'bg-slate-900 text-white dark:bg-slate-100 dark:text-slate-900'
                : 'text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800'"
        >
            {{ link.label }}
        </Link>
    </nav>
</template>
