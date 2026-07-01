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
    <nav class="mb-6 flex flex-wrap gap-2 border-b border-ink-200 pb-3 dark:border-ink-800">
        <Link
            v-for="link in links"
            :key="link.key"
            :href="link.href"
            class="rounded-control px-3 py-1.5 text-sm transition-colors"
            :class="active === link.key
                ? 'bg-ink-950 text-white dark:bg-ink-50 dark:text-ink-950'
                : 'text-ink-600 hover:bg-ink-100 dark:text-ink-300 dark:hover:bg-ink-800'"
        >
            {{ link.label }}
        </Link>
    </nav>
</template>
