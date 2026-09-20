<script setup>
// Settings hub — one card per area, each showing its current value so you can
// see the state of the workspace without opening every page. Cards are hidden
// when the signed-in role can't use them (each section re-checks server-side).
import { computed } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import {
    BuildingOffice2Icon,
    UsersIcon,
    BanknotesIcon,
    ReceiptPercentIcon,
    GlobeAsiaAustraliaIcon,
    BellAlertIcon,
    PuzzlePieceIcon,
    UserCircleIcon,
    DocumentTextIcon,
    ClipboardDocumentListIcon,
    ChevronRightIcon,
} from '@heroicons/vue/24/outline';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import { useTenantFormat } from '@/composables/useTenantFormat';

const props = defineProps({
    isAdmin: { type: Boolean, default: false },
    isFinance: { type: Boolean, default: false },
    summary: { type: Object, required: true },
    recentActivity: { type: Array, default: () => [] },
});

const { t } = useI18n();
const page = usePage();
const base = computed(() => `/app/${page.props.tenant.slug}`);

const cards = computed(() => [
    {
        key: 'company',
        href: `${base.value}/settings/company`,
        icon: BuildingOffice2Icon,
        value: props.summary.company_name,
        show: true,
    },
    {
        key: 'staff',
        href: `${base.value}/settings/staff`,
        icon: UsersIcon,
        value: t('settings.cards.staff_value', {
            n: props.summary.staff_count,
            pending: props.summary.pending_invitations,
        }),
        show: true,
    },
    {
        key: 'finance',
        href: `${base.value}/settings/finance`,
        icon: BanknotesIcon,
        value: props.summary.late_fees_enabled ? t('settings.cards.late_fees_on') : t('settings.cards.late_fees_off'),
        show: props.isFinance,
    },
    {
        key: 'invoice_template',
        href: `${base.value}/settings/invoice-template`,
        icon: ReceiptPercentIcon,
        value: t(`settings.invoice_template.layouts.${props.summary.invoice_layout}`),
        show: props.isFinance,
    },
    {
        key: 'regional',
        href: `${base.value}/settings/regional`,
        icon: GlobeAsiaAustraliaIcon,
        value: `${props.summary.timezone} · ${props.summary.currency}`,
        show: true,
    },
    {
        key: 'notifications',
        href: `${base.value}/notifications/settings`,
        icon: BellAlertIcon,
        value: t('settings.cards.provider_value', { provider: props.summary.email_provider }),
        show: props.isAdmin,
    },
    {
        key: 'integrations',
        href: `${base.value}/settings/integrations`,
        icon: PuzzlePieceIcon,
        value: t('settings.cards.ai_value', { provider: props.summary.ai_provider }),
        show: props.isAdmin,
    },
    {
        key: 'agreement_templates',
        href: `${base.value}/agreements/templates`,
        icon: DocumentTextIcon,
        value: t('settings.cards.agreement_templates_value'),
        show: true,
    },
    {
        key: 'lead_form',
        href: `${base.value}/leads/form`,
        icon: ClipboardDocumentListIcon,
        value: props.summary.lead_form_enabled ? t('settings.cards.lead_form_on') : t('settings.cards.lead_form_off'),
        show: true,
    },
    {
        key: 'personal',
        href: `${base.value}/profile`,
        icon: UserCircleIcon,
        value: t('settings.cards.personal_value'),
        show: true,
    },
].filter((card) => card.show));

// The company's timezone and date format (Settings → Regional).
const { dateTime } = useTenantFormat();
function when(value) {
    return value ? dateTime(value) : '';
}
</script>

<template>
    <AppLayout>
        <Head :title="t('settings.title')" />

        <PageHeader :title="t('settings.title')" :description="t('settings.intro')" />

        <div class="grid max-w-5xl grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <Link
                v-for="card in cards"
                :key="card.key"
                :href="card.href"
                class="group flex items-start gap-3 rounded-card border border-ink-200 bg-white p-4 shadow-subtle transition-colors duration-150 hover:border-ink-400 dark:border-ink-800 dark:bg-ink-900 dark:hover:border-ink-600"
            >
                <span class="mt-0.5 rounded-control bg-ink-100 p-2 text-ink-700 dark:bg-ink-800 dark:text-ink-200">
                    <component :is="card.icon" class="h-5 w-5" />
                </span>
                <span class="min-w-0 flex-1">
                    <span class="flex items-center justify-between gap-2">
                        <span class="font-medium text-ink-900 dark:text-ink-50">{{ t(`settings.cards.${card.key}`) }}</span>
                        <ChevronRightIcon class="h-4 w-4 shrink-0 text-ink-300 transition-transform duration-150 group-hover:translate-x-0.5 dark:text-ink-600" />
                    </span>
                    <span class="mt-0.5 block text-sm text-ink-500">{{ t(`settings.cards.${card.key}_hint`) }}</span>
                    <span class="mt-2 block truncate text-xs text-ink-400">{{ card.value }}</span>
                </span>
            </Link>
        </div>

        <!-- Recent changes (admin) -->
        <section v-if="isAdmin" class="mt-8 max-w-5xl">
            <div class="flex items-center justify-between">
                <h2 class="text-xs font-medium uppercase tracking-wide text-ink-500">{{ t('settings.recent_changes') }}</h2>
                <Link :href="`${base}/settings/activity`" class="text-sm font-medium text-ink-900 hover:underline dark:text-ink-100">
                    {{ t('settings.view_all_activity') }}
                </Link>
            </div>

            <p v-if="!recentActivity.length" class="mt-3 text-sm text-ink-500">{{ t('settings.no_activity') }}</p>
            <ul v-else class="mt-3 divide-y divide-ink-100 rounded-card border border-ink-200 bg-white text-sm shadow-subtle dark:divide-ink-800 dark:border-ink-800 dark:bg-ink-900">
                <li v-for="entry in recentActivity" :key="entry.id" class="flex flex-wrap items-center justify-between gap-2 px-4 py-2.5">
                    <span class="text-ink-700 dark:text-ink-200">
                        {{ t(`settings.actions.${entry.action}`, { subject: entry.subject_label ?? '' }) }}
                    </span>
                    <span class="text-xs text-ink-400">{{ entry.actor_label }} · {{ when(entry.created_at) }}</span>
                </li>
            </ul>
        </section>
    </AppLayout>
</template>
