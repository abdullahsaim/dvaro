<script setup>
// Tenant dashboard.
//
// Built around one question: what does this company need to DO today? The
// "Needs attention" panel answers it and everything else supports it — the
// fleet snapshot says what the vehicles are doing, the week block says what is
// coming. KPI tiles sit underneath, because a number you cannot act on is worth
// less than a row you can click.
//
// Money only appears when the server sent it (seesMoney). Staff payloads do not
// contain revenue at all, so there is nothing here to leak.
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import StatCard from '@/Components/UI/StatCard.vue';
import Button from '@/Components/UI/Button.vue';
import {
    ExclamationTriangleIcon,
    CheckCircleIcon,
    ArrowPathIcon,
} from '@heroicons/vue/24/outline';
import { useTenantFormat } from '@/composables/useTenantFormat';

const props = defineProps({
    planName: { type: String, default: null },
    subscriptionStatus: { type: String, default: null },
    trialDaysRemaining: { type: Number, default: null },
    summary: { type: Object, default: () => ({}) },
    seesMoney: { type: Boolean, default: false },
    operational: {
        type: Object,
        default: () => ({ attention: { groups: [], total: 0, urgent: 0 }, fleet: { statuses: [], total: 0, utilisation: 0 }, week: {} }),
    },
});

const { t } = useI18n();
const { date, money } = useTenantFormat();
const page = usePage();

const user = computed(() => page.props.auth.user);
const tenant = computed(() => page.props.tenant);
const base = computed(() => `/app/${tenant.value.slug}`);

const attention = computed(() => props.operational?.attention ?? { groups: [], total: 0, urgent: 0 });
const fleet = computed(() => props.operational?.fleet ?? { statuses: [], total: 0, utilisation: 0 });
const week = computed(() => props.operational?.week ?? {});
// null for staff — the server never sends it, so there is nothing to hide.
const cash = computed(() => props.operational?.money ?? null);

// ── Polling ─────────────────────────────────────────────────────────────────
// CLAUDE.md: dashboard KPIs refresh by polling (30–60s), not WebSockets, in v1.
// Only the 'operational' prop is re-fetched, and only while the tab is visible
// — a dashboard left open on a forgotten tab must not keep hitting the VPS.
const POLL_MS = 60000;
const refreshing = ref(false);
let timer = null;

function refresh() {
    if (document.hidden || refreshing.value) return;

    router.reload({
        only: ['operational'],
        onStart: () => (refreshing.value = true),
        onFinish: () => (refreshing.value = false),
    });
}

function startPolling() {
    stopPolling();
    timer = window.setInterval(refresh, POLL_MS);
}

function stopPolling() {
    if (timer) window.clearInterval(timer);
    timer = null;
}

function onVisibilityChange() {
    if (document.hidden) {
        stopPolling();
    } else {
        refresh(); // catch up on whatever changed while we were away
        startPolling();
    }
}

onMounted(() => {
    startPolling();
    document.addEventListener('visibilitychange', onVisibilityChange);
});

onBeforeUnmount(() => {
    stopPolling();
    document.removeEventListener('visibilitychange', onVisibilityChange);
});

// ── Presentation helpers ────────────────────────────────────────────────────

// Each attention group knows which screen it belongs to.
const groupLinks = {
    overdue_invoices: 'invoices',
    fleet_expiries: 'fleet',
    agreements_ending: 'agreements',
    unconverted_leads: 'leads',
    vehicles_off_road: 'fleet',
    workshop_jobs: 'workshop',
};

const statusColours = {
    available: 'bg-success-600',
    rented: 'bg-ink-800 dark:bg-ink-200',
    maintenance: 'bg-warning-500',
    suspended: 'bg-ink-400',
    accident: 'bg-danger-600',
    reserved: 'bg-ink-500',
};

// A row's supporting text — the shape differs per group, so each one says what
// it means rather than relying on position.
function detail(group, item) {
    if (item.detail_key === 'days_overdue') {
        return t('dashboard.attention.days_overdue', { n: item.detail_value });
    }
    if (item.detail_key === 'vehicle') {
        return `${t('dashboard.attention.vehicle')}: ${item.detail_value}`;
    }
    if (item.detail_key === 'phone') {
        return item.detail_value;
    }
    if (item.detail_key === 'status') {
        return t(`fleet.statuses.${item.detail_value}`, item.detail_value);
    }
    // registration / insurance / service / service_km
    return `${t(`dashboard.attention.due.${item.detail_key}`)}: ${item.detail_key === 'service_km' ? item.detail_value : date(item.detail_value)}`;
}

// ── Revenue sparkline ───────────────────────────────────────────────────────
// Hand-drawn SVG rather than a charting library: six points do not justify
// 100KB of JavaScript on a page that polls itself.
const SPARK = { w: 240, h: 48, pad: 3 };

const spark = computed(() => {
    const months = cash.value?.trend ?? [];
    if (months.length < 2) return null;

    const values = months.map((m) => m.revenue);
    const max = Math.max(...values, 1);
    const stepX = (SPARK.w - SPARK.pad * 2) / (months.length - 1);
    const scaleY = (v) => SPARK.h - SPARK.pad - (v / max) * (SPARK.h - SPARK.pad * 2);

    const points = months.map((m, i) => ({
        ...m,
        x: SPARK.pad + i * stepX,
        y: scaleY(m.revenue),
    }));

    return {
        points,
        line: points.map((p, i) => `${i === 0 ? 'M' : 'L'}${p.x.toFixed(1)},${p.y.toFixed(1)}`).join(' '),
        // Closed path for the soft fill under the line.
        area: `M${points[0].x.toFixed(1)},${SPARK.h} `
            + points.map((p) => `L${p.x.toFixed(1)},${p.y.toFixed(1)}`).join(' ')
            + ` L${points[points.length - 1].x.toFixed(1)},${SPARK.h} Z`,
        last: points[points.length - 1],
    };
});

// Receivables as proportions, for the ageing bar.
const ageing = computed(() => {
    const r = cash.value?.receivables;
    if (!r || r.total <= 0) return [];

    return [
        { key: 'current', value: r.current, class: 'bg-success-600' },
        { key: 'late_30', value: r.late_30, class: 'bg-warning-500' },
        { key: 'late_over_30', value: r.late_over_30, class: 'bg-danger-600' },
    ]
        .filter((b) => b.value > 0)
        .map((b) => ({ ...b, pct: (b.value / r.total) * 100 }));
});

const weekTiles = computed(() => [
    { key: 'starting_today', value: week.value.starting_today ?? 0 },
    { key: 'ending_today', value: week.value.ending_today ?? 0 },
    { key: 'starting', value: week.value.starting ?? 0 },
    { key: 'ending', value: week.value.ending ?? 0 },
    { key: 'services_booked', value: week.value.services_booked ?? 0 },
    { key: 'new_leads', value: week.value.new_leads ?? 0 },
]);
</script>

<template>
    <AppLayout>
        <Head title="Dashboard" />

        <PageHeader
            :title="t('dashboard.welcome', { name: user.name })"
            :description="tenant.name"
        >
            <template #actions>
                <button
                    type="button"
                    class="inline-flex h-9 items-center gap-1.5 rounded-control px-2 text-sm text-ink-500 transition-colors hover:text-ink-900 dark:hover:text-ink-100"
                    :title="t('dashboard.refresh')"
                    @click="refresh"
                >
                    <ArrowPathIcon class="h-4 w-4" :class="refreshing ? 'animate-spin' : ''" />
                    <span class="sr-only">{{ t('dashboard.refresh') }}</span>
                </button>
                <Button variant="secondary" @click="router.visit(`${base}/reports`)">
                    {{ t('dashboard.view_reports') }}
                </Button>
            </template>
        </PageHeader>

        <!-- ── Needs attention ─────────────────────────────────────────── -->
        <section class="rounded-card border border-ink-200 bg-white shadow-subtle dark:border-ink-800 dark:bg-ink-900">
            <div class="flex flex-wrap items-center justify-between gap-2 border-b border-ink-200 p-4 dark:border-ink-800">
                <div class="flex items-center gap-2">
                    <ExclamationTriangleIcon
                        v-if="attention.total > 0"
                        class="h-5 w-5"
                        :class="attention.urgent > 0 ? 'text-danger-600' : 'text-warning-500'"
                    />
                    <CheckCircleIcon v-else class="h-5 w-5 text-success-600" />
                    <h2 class="text-sm font-semibold text-ink-900 dark:text-ink-100">
                        {{ t('dashboard.attention.title') }}
                    </h2>
                </div>
                <span v-if="attention.total > 0" class="text-sm text-ink-500">
                    {{ t('dashboard.attention.count', { n: attention.total }) }}
                </span>
            </div>

            <!-- All clear -->
            <div v-if="attention.groups.length === 0" class="p-8 text-center">
                <p class="text-sm font-medium text-ink-900 dark:text-ink-100">{{ t('dashboard.attention.all_clear') }}</p>
                <p class="mt-1 text-sm text-ink-500">{{ t('dashboard.attention.all_clear_hint') }}</p>
            </div>

            <div v-else class="divide-y divide-ink-200 dark:divide-ink-800">
                <div v-for="group in attention.groups" :key="group.key" class="p-4">
                    <div class="flex flex-wrap items-baseline justify-between gap-2">
                        <h3 class="flex items-center gap-2 text-sm font-medium text-ink-800 dark:text-ink-100">
                            <span
                                class="inline-block h-1.5 w-1.5 rounded-full"
                                :class="group.severity === 'urgent' ? 'bg-danger-600' : 'bg-warning-500'"
                            />
                            {{ t(`dashboard.attention.groups.${group.key}`) }}
                            <span class="text-ink-400">({{ group.count }})</span>
                        </h3>
                        <Link
                            v-if="group.count > group.items.length"
                            :href="`${base}/${groupLinks[group.key]}`"
                            class="text-xs text-ink-500 underline underline-offset-4 hover:text-ink-900 dark:hover:text-ink-100"
                        >
                            {{ t('dashboard.attention.and_more', { n: group.count - group.items.length }) }}
                        </Link>
                    </div>

                    <ul class="mt-2 space-y-1">
                        <li v-for="item in group.items" :key="`${group.key}-${item.id}`">
                            <Link
                                :href="`${base}/${item.url}`"
                                class="flex items-center justify-between gap-3 rounded-control px-2 py-1.5 text-sm transition-colors hover:bg-ink-50 dark:hover:bg-ink-950"
                            >
                                <span class="min-w-0 flex-1 truncate text-ink-800 dark:text-ink-100">{{ item.label }}</span>
                                <span class="shrink-0 text-xs text-ink-500">{{ detail(group, item) }}</span>
                                <span
                                    v-if="seesMoney && item.amount !== undefined"
                                    class="shrink-0 text-sm font-medium tabular-nums text-ink-900 dark:text-ink-100"
                                >
                                    {{ money(item.amount) }}
                                </span>
                            </Link>
                        </li>
                    </ul>
                </div>
            </div>
        </section>

        <!-- ── Money (admin + accounts; absent from a staff payload) ───── -->
        <div v-if="cash" class="mt-4 grid grid-cols-1 gap-4 lg:grid-cols-2">
            <!-- Cash in -->
            <section class="rounded-card border border-ink-200 bg-white p-4 shadow-subtle dark:border-ink-800 dark:bg-ink-900">
                <div class="flex flex-wrap items-baseline justify-between gap-2">
                    <h2 class="text-sm font-semibold text-ink-900 dark:text-ink-100">{{ t('dashboard.money.title') }}</h2>
                    <span class="text-xs text-ink-400">{{ t('dashboard.money.hint') }}</span>
                </div>

                <div class="mt-3 flex flex-wrap items-end justify-between gap-4">
                    <div>
                        <p class="text-2xl font-semibold tabular-nums text-ink-900 dark:text-ink-50">
                            {{ money(cash.revenue_this_month) }}
                        </p>
                        <p class="mt-1 text-xs text-ink-500">
                            <span
                                v-if="cash.revenue_change_pct !== null"
                                :class="cash.revenue_change_pct >= 0 ? 'text-success-700 dark:text-success-500' : 'text-danger-600'"
                            >
                                {{ cash.revenue_change_pct >= 0 ? '+' : '' }}{{ cash.revenue_change_pct }}%
                            </span>
                            <span v-else>{{ t('dashboard.money.no_comparison') }}</span>
                            <span class="text-ink-400"> · {{ t('dashboard.money.last_month', { amount: money(cash.revenue_last_month) }) }}</span>
                        </p>
                    </div>

                    <!-- Six months of cash received. -->
                    <svg
                        v-if="spark"
                        :viewBox="`0 0 ${SPARK.w} ${SPARK.h}`"
                        class="h-12 w-full max-w-[240px] shrink-0 overflow-visible"
                        role="img"
                        :aria-label="t('dashboard.money.trend_label')"
                    >
                        <path :d="spark.area" class="fill-ink-900/5 dark:fill-ink-100/10" />
                        <path
                            :d="spark.line"
                            fill="none"
                            stroke-width="1.5"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            class="stroke-ink-900 dark:stroke-ink-100"
                        />
                        <circle :cx="spark.last.x" :cy="spark.last.y" r="2.5" class="fill-ink-900 dark:fill-ink-100" />
                    </svg>
                </div>

                <dl class="mt-4 grid grid-cols-2 gap-3 border-t border-ink-200 pt-3 dark:border-ink-800">
                    <div>
                        <dt class="text-xs text-ink-500">{{ t('dashboard.money.expenses') }}</dt>
                        <dd class="mt-0.5 text-sm font-medium tabular-nums text-ink-900 dark:text-ink-100">
                            {{ money(cash.expenses_this_month) }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs text-ink-500">{{ t('dashboard.money.net') }}</dt>
                        <dd
                            class="mt-0.5 text-sm font-medium tabular-nums"
                            :class="cash.net_this_month >= 0 ? 'text-ink-900 dark:text-ink-100' : 'text-danger-600'"
                        >
                            {{ money(cash.net_this_month) }}
                        </dd>
                    </div>
                </dl>
            </section>

            <!-- Owed to you -->
            <section class="rounded-card border border-ink-200 bg-white p-4 shadow-subtle dark:border-ink-800 dark:bg-ink-900">
                <div class="flex flex-wrap items-baseline justify-between gap-2">
                    <h2 class="text-sm font-semibold text-ink-900 dark:text-ink-100">{{ t('dashboard.money.owed') }}</h2>
                    <span class="text-sm font-semibold tabular-nums text-ink-900 dark:text-ink-100">
                        {{ money(cash.receivables.total) }}
                    </span>
                </div>

                <template v-if="cash.receivables.total > 0">
                    <div class="mt-4 flex h-2.5 overflow-hidden rounded-full bg-ink-100 dark:bg-ink-800">
                        <div
                            v-for="band in ageing"
                            :key="band.key"
                            class="h-full"
                            :class="band.class"
                            :style="{ width: `${band.pct}%` }"
                            :title="`${t(`dashboard.money.ageing.${band.key}`)}: ${money(band.value)}`"
                        />
                    </div>

                    <dl class="mt-4 space-y-2">
                        <div
                            v-for="key in ['current', 'late_30', 'late_over_30']"
                            :key="key"
                            class="flex items-center justify-between gap-3 text-sm"
                        >
                            <dt class="flex items-center gap-2 text-ink-600 dark:text-ink-300">
                                <span
                                    class="inline-block h-2 w-2 rounded-full"
                                    :class="{ current: 'bg-success-600', late_30: 'bg-warning-500', late_over_30: 'bg-danger-600' }[key]"
                                />
                                {{ t(`dashboard.money.ageing.${key}`) }}
                            </dt>
                            <dd class="tabular-nums font-medium text-ink-900 dark:text-ink-100">
                                {{ money(cash.receivables[key]) }}
                            </dd>
                        </div>
                    </dl>

                    <Link
                        :href="`${base}/invoices`"
                        class="mt-4 inline-block text-xs text-ink-500 underline underline-offset-4 hover:text-ink-900 dark:hover:text-ink-100"
                    >
                        {{ t('dashboard.money.view_invoices') }}
                    </Link>
                </template>

                <p v-else class="mt-6 text-center text-sm text-ink-500">{{ t('dashboard.money.nothing_owed') }}</p>
            </section>
        </div>

        <!-- ── Fleet + week ────────────────────────────────────────────── -->
        <div class="mt-4 grid grid-cols-1 gap-4 lg:grid-cols-2">
            <!-- Fleet snapshot -->
            <section class="rounded-card border border-ink-200 bg-white p-4 shadow-subtle dark:border-ink-800 dark:bg-ink-900">
                <div class="flex items-baseline justify-between gap-2">
                    <h2 class="text-sm font-semibold text-ink-900 dark:text-ink-100">{{ t('dashboard.fleet.title') }}</h2>
                    <span class="text-sm text-ink-500">
                        {{ t('dashboard.fleet.on_road', { pct: fleet.utilisation }) }}
                    </span>
                </div>

                <template v-if="fleet.total > 0">
                    <!-- Proportional bar: each segment is a link to that filter. -->
                    <div class="mt-4 flex h-2.5 overflow-hidden rounded-full bg-ink-100 dark:bg-ink-800">
                        <Link
                            v-for="s in fleet.statuses.filter((x) => x.count > 0)"
                            :key="s.status"
                            :href="`${base}/fleet?status=${s.status}`"
                            class="h-full transition-opacity hover:opacity-75"
                            :class="statusColours[s.status]"
                            :style="{ width: `${s.percentage}%` }"
                            :title="`${t(`fleet.statuses.${s.status}`)}: ${s.count}`"
                        />
                    </div>

                    <ul class="mt-4 grid grid-cols-2 gap-x-4 gap-y-2 sm:grid-cols-3">
                        <li v-for="s in fleet.statuses" :key="s.status">
                            <Link
                                :href="`${base}/fleet?status=${s.status}`"
                                class="flex items-center gap-2 text-sm transition-colors hover:text-ink-900 dark:hover:text-ink-100"
                                :class="s.count === 0 ? 'text-ink-400' : 'text-ink-600 dark:text-ink-300'"
                            >
                                <span class="inline-block h-2 w-2 shrink-0 rounded-full" :class="statusColours[s.status]" />
                                <span class="truncate">{{ t(`fleet.statuses.${s.status}`) }}</span>
                                <span class="ml-auto tabular-nums font-medium">{{ s.count }}</span>
                            </Link>
                        </li>
                    </ul>
                </template>

                <p v-else class="mt-6 text-center text-sm text-ink-500">{{ t('dashboard.fleet.empty') }}</p>
            </section>

            <!-- This week -->
            <section class="rounded-card border border-ink-200 bg-white p-4 shadow-subtle dark:border-ink-800 dark:bg-ink-900">
                <h2 class="text-sm font-semibold text-ink-900 dark:text-ink-100">{{ t('dashboard.week.title') }}</h2>
                <p class="mt-1 text-sm text-ink-500">{{ t('dashboard.week.hint') }}</p>

                <dl class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-3">
                    <div
                        v-for="tile in weekTiles"
                        :key="tile.key"
                        class="rounded-control border border-ink-200 p-3 dark:border-ink-800"
                    >
                        <dt class="text-xs text-ink-500">{{ t(`dashboard.week.${tile.key}`) }}</dt>
                        <dd class="mt-1 text-xl font-semibold tabular-nums text-ink-900 dark:text-ink-50">{{ tile.value }}</dd>
                    </div>
                </dl>
            </section>
        </div>

        <!-- ── KPI tiles ───────────────────────────────────────────────── -->
        <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-3">
            <!-- Only when the money block is absent: it already states the same
                 total, and the dashboard should never say a number twice. -->
            <StatCard
                v-if="seesMoney && !cash"
                :label="t('dashboard.outstanding_balance')"
                :value="money(summary.outstanding_balance ?? 0)"
            />
            <StatCard :label="t('dashboard.active_rentals')" :value="summary.active_rentals ?? 0" />
            <StatCard :label="t('dashboard.vehicles_available')" :value="summary.vehicles_available ?? 0" />
        </div>

        <!-- ── Subscription ───────────────────────────────────────────── -->
        <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <StatCard :label="t('dashboard.plan')" :value="planName ?? t('dashboard.no_plan')" />
            <StatCard :label="t('dashboard.subscription_status')">
                <span class="capitalize">{{ subscriptionStatus ?? '—' }}</span>
            </StatCard>
            <StatCard
                v-if="trialDaysRemaining !== null"
                :label="t('dashboard.trial_days_remaining')"
                :value="trialDaysRemaining"
            />
        </div>
    </AppLayout>
</template>
