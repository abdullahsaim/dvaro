<script setup>
// Finance → Expenses. Summary (this month / FY / GST), filters with date
// presets, spend-by-category, the expense list, a slide-over add/edit form
// (GST auto = 1/11 of a GST-inclusive total, overridable), void dialog, and
// category management (admin/accounts). Money: AUD in inputs → cents on submit.
import { computed, ref, watch } from 'vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import {
    PlusIcon,
    PaperClipIcon,
    TagIcon,
    XMarkIcon,
    ReceiptPercentIcon,
    ChartBarIcon,
} from '@heroicons/vue/24/outline';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import StatCard from '@/Components/UI/StatCard.vue';
import DataTable from '@/Components/UI/DataTable.vue';
import StatusBadge from '@/Components/UI/StatusBadge.vue';
import EmptyState from '@/Components/UI/EmptyState.vue';
import Button from '@/Components/UI/Button.vue';
import Input from '@/Components/UI/Input.vue';
import Select from '@/Components/UI/Select.vue';
import Textarea from '@/Components/UI/Textarea.vue';
import Modal from '@/Components/UI/Modal.vue';
import { useCurrency } from '@/composables/useCurrency';

const props = defineProps({
    expenses: { type: Object, required: true }, // paginator
    filters: { type: Object, required: true }, // { from, to, category, vehicle, voided }
    summary: { type: Object, required: true }, // { filtered{totals,by_category}, month, fy, fy_label, fy_from, fy_to }
    categories: { type: Array, required: true },
    vehicles: { type: Array, required: true },
    paymentMethods: { type: Array, required: true },
    canManage: { type: Boolean, default: false },
});

const { t } = useI18n();
const { formatAUD, toCents } = useCurrency();
const page = usePage();
const base = computed(() => `/app/${page.props.tenant.slug}/expenses`);

// ── Formatting ───────────────────────────────────────────────────────────────
const dateFormat = new Intl.DateTimeFormat('en-AU', { day: '2-digit', month: 'short', year: 'numeric' });
function formatDate(value) {
    if (!value) return '';
    const [y, m, d] = String(value).slice(0, 10).split('-').map(Number);
    return dateFormat.format(new Date(y, m - 1, d));
}
const iso = (d) => `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
const vehicleLabel = (v) => (v ? `${v.registration_number} · ${v.make} ${v.model}` : '');

// ── Filters ──────────────────────────────────────────────────────────────────
const range = ref({ from: props.filters.from, to: props.filters.to });

const presets = computed(() => {
    const now = new Date();
    return [
        { key: 'this_month', from: iso(new Date(now.getFullYear(), now.getMonth(), 1)), to: iso(new Date(now.getFullYear(), now.getMonth() + 1, 0)) },
        { key: 'last_month', from: iso(new Date(now.getFullYear(), now.getMonth() - 1, 1)), to: iso(new Date(now.getFullYear(), now.getMonth(), 0)) },
        { key: 'this_fy', from: props.summary.fy_from, to: props.summary.fy_to },
    ];
});
const activePreset = computed(() => presets.value.find((p) => p.from === props.filters.from && p.to === props.filters.to)?.key ?? null);

function applyFilters(overrides = {}) {
    const query = {
        from: range.value.from,
        to: range.value.to,
        category: props.filters.category || undefined,
        vehicle: props.filters.vehicle || undefined,
        voided: props.filters.voided ? 1 : undefined,
        ...overrides,
    };
    Object.keys(query).forEach((k) => (query[k] === undefined || query[k] === null || query[k] === '') && delete query[k]);
    router.get(base.value, query, { preserveState: true, preserveScroll: true, replace: true });
}

function usePreset(p) {
    range.value = { from: p.from, to: p.to };
    applyFilters();
}

const categoryShare = computed(() => {
    const rows = props.summary.filtered.by_category;
    const total = props.summary.filtered.totals.total || 1;
    return rows.map((r) => ({ ...r, pct: Math.round((r.total / total) * 100) }));
});

// ── Add / edit slide-over ────────────────────────────────────────────────────
const panelOpen = ref(false);
const editing = ref(null); // expense being edited, or null for new
const gstTouched = ref(false);

const form = useForm({
    expense_date: iso(new Date()),
    expense_category_id: '',
    description: '',
    supplier: '',
    amount: '', // AUD
    includes_gst: true,
    gst: '', // AUD
    payment_method: 'card',
    vehicle_id: '',
    notes: '',
    receipt: null,
    remove_receipt: false,
});

const visibleCategories = computed(() =>
    props.categories.filter((c) => !c.is_hidden || c.id === editing.value?.expense_category_id),
);

function autoGst(amount) {
    const cents = toCents(amount || 0);
    return (Math.round(cents / 11) / 100).toFixed(2);
}

watch(() => [form.amount, form.includes_gst], () => {
    if (!form.includes_gst) {
        form.gst = '0.00';
        gstTouched.value = false;
    } else if (!gstTouched.value) {
        form.gst = form.amount === '' ? '' : autoGst(form.amount);
    }
});

function resetGst() {
    gstTouched.value = false;
    form.gst = form.amount === '' ? '' : autoGst(form.amount);
}

function openNew() {
    editing.value = null;
    form.reset();
    form.clearErrors();
    form.expense_date = iso(new Date());
    form.expense_category_id = props.categories.find((c) => !c.is_hidden)?.id ?? '';
    gstTouched.value = false;
    panelOpen.value = true;
}

function openEdit(expense) {
    editing.value = expense;
    form.clearErrors();
    form.expense_date = String(expense.expense_date).slice(0, 10);
    form.expense_category_id = expense.expense_category_id;
    form.description = expense.description;
    form.supplier = expense.supplier ?? '';
    form.amount = (expense.amount_total / 100).toFixed(2);
    form.includes_gst = expense.includes_gst;
    form.gst = (expense.gst_amount / 100).toFixed(2);
    gstTouched.value = expense.includes_gst && expense.gst_amount !== Math.round(expense.amount_total / 11);
    form.payment_method = expense.payment_method;
    form.vehicle_id = expense.vehicle_id ?? '';
    form.notes = expense.notes ?? '';
    form.receipt = null;
    form.remove_receipt = false;
    panelOpen.value = true;
}

const fileInput = ref(null);
function pickReceipt(event) {
    form.receipt = event.target.files?.[0] ?? null;
    form.remove_receipt = false;
}

function submit() {
    const url = editing.value ? `${base.value}/${editing.value.id}` : base.value;

    form.transform((data) => ({
        ...(editing.value ? { _method: 'put' } : {}),
        expense_date: data.expense_date,
        expense_category_id: data.expense_category_id,
        description: data.description,
        supplier: data.supplier || null,
        amount_total: toCents(data.amount || 0),
        includes_gst: data.includes_gst ? 1 : 0,
        gst_amount: data.includes_gst && gstTouched.value ? toCents(data.gst || 0) : null,
        payment_method: data.payment_method,
        vehicle_id: data.vehicle_id || null,
        notes: data.notes || null,
        receipt: data.receipt,
        remove_receipt: data.remove_receipt ? 1 : 0,
    })).post(url, {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            panelOpen.value = false;
            if (fileInput.value) fileInput.value.value = '';
        },
    });
}

// Field errors come back keyed by the SERVER names.
const err = (key) => form.errors[key];

// ── Void ─────────────────────────────────────────────────────────────────────
const voiding = ref(null);
const voidForm = useForm({ reason: '' });

function openVoid(expense) {
    voiding.value = expense;
    voidForm.reset();
    voidForm.clearErrors();
}

function submitVoid() {
    voidForm.post(`${base.value}/${voiding.value.id}/void`, {
        preserveScroll: true,
        onSuccess: () => (voiding.value = null),
    });
}

// ── Categories (admin / accounts) ────────────────────────────────────────────
const categoriesOpen = ref(false);
const newCategory = useForm({ name: '' });
const categoryBase = computed(() => `/app/${page.props.tenant.slug}/expense-categories`);

function addCategory() {
    newCategory.post(categoryBase.value, { preserveScroll: true, onSuccess: () => newCategory.reset() });
}

const renaming = ref({});
function saveCategory(category, patch = {}) {
    router.put(`${categoryBase.value}/${category.id}`, {
        name: patch.name ?? renaming.value[category.id] ?? category.name,
        is_hidden: patch.is_hidden ?? category.is_hidden,
    }, {
        preserveScroll: true,
        onSuccess: () => delete renaming.value[category.id],
    });
}

const methodLabel = (m) => t(`expenses.methods.${m}`);
</script>

<template>
    <AppLayout>
        <Head :title="t('expenses.title')" />

        <PageHeader :title="t('expenses.title')" :description="t('expenses.intro')">
            <template #actions>
                <Link
                    :href="`/app/${page.props.tenant.slug}/reports/expenses?from=${filters.from}&to=${filters.to}`"
                    class="inline-flex h-10 items-center gap-2 rounded-control px-4 text-sm font-medium text-ink-700 transition-colors hover:bg-ink-100 dark:text-ink-300 dark:hover:bg-ink-800"
                >
                    <ChartBarIcon class="h-4 w-4" />{{ t('expenses.report') }}
                </Link>
                <Button v-if="canManage" variant="secondary" @click="categoriesOpen = true">
                    <TagIcon class="h-4 w-4" />{{ t('expenses.categories') }}
                </Button>
                <Button @click="openNew"><PlusIcon class="h-4 w-4" />{{ t('expenses.record') }}</Button>
            </template>
        </PageHeader>

        <!-- Summary -->
        <dl class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-3">
            <StatCard :label="t('expenses.stats.this_month')" :value="formatAUD(summary.month.total)" />
            <StatCard :label="t('expenses.stats.fy_to_date', { fy: summary.fy_label })" :value="formatAUD(summary.fy.total)" />
            <StatCard :label="t('expenses.stats.gst_fy', { fy: summary.fy_label })" :value="formatAUD(summary.fy.gst)" />
        </dl>

        <!-- Filters -->
        <div class="mb-4 flex flex-wrap items-end gap-3 rounded-card border border-ink-200 bg-white p-4 shadow-subtle dark:border-ink-800 dark:bg-ink-900">
            <div class="flex flex-wrap gap-2">
                <button
                    v-for="p in presets"
                    :key="p.key"
                    type="button"
                    class="rounded-full px-3 py-1 text-sm transition-colors"
                    :class="activePreset === p.key
                        ? 'bg-ink-950 text-white dark:bg-ink-50 dark:text-ink-950'
                        : 'bg-ink-100 text-ink-600 hover:bg-ink-200 dark:bg-ink-800 dark:text-ink-300 dark:hover:bg-ink-700'"
                    @click="usePreset(p)"
                >
                    {{ p.key === 'this_fy' ? summary.fy_label : t(`expenses.presets.${p.key}`) }}
                </button>
            </div>
            <Input v-model="range.from" type="date" :label="t('expenses.filters.from')" class="w-40" @change="applyFilters()" />
            <Input v-model="range.to" type="date" :label="t('expenses.filters.to')" class="w-40" @change="applyFilters()" />
            <Select :model-value="filters.category ?? ''" :label="t('expenses.fields.category')" class="w-48" @update:model-value="(v) => applyFilters({ category: v || undefined })">
                <option value="">{{ t('expenses.filters.all_categories') }}</option>
                <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
            </Select>
            <Select :model-value="filters.vehicle ?? ''" :label="t('expenses.fields.vehicle')" class="w-56" @update:model-value="(v) => applyFilters({ vehicle: v || undefined })">
                <option value="">{{ t('expenses.filters.all_vehicles') }}</option>
                <option v-for="v in vehicles" :key="v.id" :value="v.id">{{ vehicleLabel(v) }}</option>
            </Select>
            <label class="flex h-10 items-center gap-2 text-sm text-ink-600 dark:text-ink-300">
                <input
                    type="checkbox"
                    class="h-4 w-4 rounded border-ink-300 accent-ink-900 dark:border-ink-700 dark:accent-ink-100"
                    :checked="filters.voided"
                    @change="applyFilters({ voided: $event.target.checked ? 1 : undefined })"
                />
                {{ t('expenses.filters.show_voided') }}
            </label>
        </div>

        <!-- Spend by category (filtered range) -->
        <div v-if="categoryShare.length" class="mb-6 rounded-card border border-ink-200 bg-white p-4 shadow-subtle dark:border-ink-800 dark:bg-ink-900">
            <div class="mb-3 flex flex-wrap items-baseline justify-between gap-2">
                <h2 class="text-sm font-semibold text-ink-900 dark:text-ink-50">{{ t('expenses.by_category') }}</h2>
                <p class="text-sm text-ink-500">
                    {{ t('expenses.filtered_total', { total: formatAUD(summary.filtered.totals.total), gst: formatAUD(summary.filtered.totals.gst) }) }}
                </p>
            </div>
            <div class="space-y-2">
                <div v-for="row in categoryShare" :key="row.category" class="grid grid-cols-[minmax(0,10rem)_1fr_6.5rem] items-center gap-3 text-sm">
                    <span class="truncate text-ink-700 dark:text-ink-200">{{ row.category }}</span>
                    <div class="h-2 overflow-hidden rounded-full bg-ink-100 dark:bg-ink-800">
                        <div class="h-full rounded-full bg-ink-900 transition-all duration-300 dark:bg-ink-100" :style="{ width: `${row.pct}%` }" />
                    </div>
                    <span class="text-right tabular-nums text-ink-900 dark:text-ink-50">{{ formatAUD(row.total) }}</span>
                </div>
            </div>
        </div>

        <!-- List -->
        <DataTable :columns="7" :empty="expenses.data.length === 0" :pagination="expenses">
            <template #head>
                <th class="px-4 py-3">{{ t('expenses.fields.date') }}</th>
                <th class="px-4 py-3">{{ t('expenses.fields.description') }}</th>
                <th class="px-4 py-3">{{ t('expenses.fields.category') }}</th>
                <th class="px-4 py-3">{{ t('expenses.fields.vehicle') }}</th>
                <th class="px-4 py-3 text-right">{{ t('expenses.fields.total') }}</th>
                <th class="px-4 py-3 text-center">{{ t('expenses.fields.receipt') }}</th>
                <th class="px-4 py-3 text-right">{{ t('common.actions') }}</th>
            </template>

            <tr
                v-for="e in expenses.data"
                :key="e.id"
                class="text-ink-700 transition-colors dark:text-ink-200"
                :class="e.voided_at ? 'opacity-60' : ''"
            >
                <td class="whitespace-nowrap px-4 py-3 tabular-nums">{{ formatDate(e.expense_date) }}</td>
                <td class="px-4 py-3">
                    <p class="font-medium text-ink-900 dark:text-ink-50" :class="e.voided_at ? 'line-through' : ''">{{ e.description }}</p>
                    <p class="text-xs text-ink-500">
                        {{ [e.supplier, methodLabel(e.payment_method)].filter(Boolean).join(' · ') }}
                    </p>
                    <p v-if="e.voided_at" class="mt-0.5 text-xs text-danger-600 dark:text-danger-500">
                        {{ t('expenses.voided_label', { reason: e.void_reason }) }}
                    </p>
                </td>
                <td class="px-4 py-3">{{ e.category?.name }}</td>
                <td class="px-4 py-3 text-sm">{{ e.vehicle?.registration_number ?? t('common.none') }}</td>
                <td class="whitespace-nowrap px-4 py-3 text-right tabular-nums">
                    <span class="font-medium text-ink-900 dark:text-ink-50">{{ formatAUD(e.amount_total) }}</span>
                    <span class="block text-xs text-ink-500">
                        {{ e.includes_gst ? t('expenses.gst_short', { gst: formatAUD(e.gst_amount) }) : t('expenses.no_gst') }}
                    </span>
                </td>
                <td class="px-4 py-3 text-center">
                    <a
                        v-if="e.has_receipt"
                        :href="`${base}/${e.id}/receipt`"
                        target="_blank"
                        rel="noopener"
                        class="inline-flex rounded-control p-1.5 text-ink-500 transition-colors hover:bg-ink-100 hover:text-ink-900 dark:hover:bg-ink-800 dark:hover:text-ink-100"
                        :aria-label="t('expenses.view_receipt')"
                    >
                        <PaperClipIcon class="h-4 w-4" />
                    </a>
                </td>
                <td class="px-4 py-3">
                    <div class="flex justify-end gap-3">
                        <StatusBadge v-if="e.voided_at" variant="neutral" :label="t('expenses.voided')" />
                        <button v-if="e.can_edit" type="button" class="text-sm font-medium text-ink-900 hover:underline dark:text-ink-100" @click="openEdit(e)">
                            {{ t('common.edit') }}
                        </button>
                        <button v-if="e.can_void" type="button" class="text-sm text-danger-600 hover:underline dark:text-danger-500" @click="openVoid(e)">
                            {{ t('expenses.void') }}
                        </button>
                    </div>
                </td>
            </tr>

            <template #empty>
                <EmptyState :title="t('expenses.empty')" :message="t('expenses.empty_hint')">
                    <template #icon><ReceiptPercentIcon class="h-6 w-6" /></template>
                    <template #action>
                        <Button @click="openNew">{{ t('expenses.record') }}</Button>
                    </template>
                </EmptyState>
            </template>
        </DataTable>

        <!-- Slide-over: record / edit -->
        <Teleport to="body">
            <Transition enter-active-class="transition-opacity duration-200" enter-from-class="opacity-0" leave-active-class="transition-opacity duration-150" leave-to-class="opacity-0">
                <div v-if="panelOpen" class="fixed inset-0 z-40 bg-ink-950/40" @click="panelOpen = false" />
            </Transition>
            <Transition
                enter-active-class="transition-transform duration-300 ease-out"
                enter-from-class="translate-x-full"
                leave-active-class="transition-transform duration-200 ease-in"
                leave-to-class="translate-x-full"
            >
                <aside
                    v-if="panelOpen"
                    class="fixed inset-y-0 right-0 z-50 flex w-full max-w-md flex-col bg-white shadow-xl dark:bg-ink-900"
                    role="dialog"
                    aria-modal="true"
                    :aria-label="editing ? t('expenses.edit') : t('expenses.record')"
                >
                    <header class="flex items-center justify-between border-b border-ink-200 px-5 py-4 dark:border-ink-800">
                        <h2 class="text-base font-semibold text-ink-900 dark:text-ink-50">{{ editing ? t('expenses.edit') : t('expenses.record') }}</h2>
                        <button type="button" class="rounded-control p-1.5 text-ink-500 hover:bg-ink-100 dark:hover:bg-ink-800" :aria-label="t('common.close')" @click="panelOpen = false">
                            <XMarkIcon class="h-5 w-5" />
                        </button>
                    </header>

                    <form id="expense-form" class="flex-1 space-y-4 overflow-y-auto px-5 py-5" @submit.prevent="submit">
                        <div class="grid grid-cols-2 gap-3">
                            <Input v-model="form.expense_date" type="date" required :label="t('expenses.fields.date')" :error="err('expense_date')" />
                            <Select v-model="form.expense_category_id" :label="t('expenses.fields.category')" :error="err('expense_category_id')">
                                <option v-for="c in visibleCategories" :key="c.id" :value="c.id">{{ c.name }}</option>
                            </Select>
                        </div>
                        <Input v-model="form.description" required :label="t('expenses.fields.description')" :placeholder="t('expenses.description_placeholder')" :error="err('description')" />
                        <Input v-model="form.supplier" :label="t('expenses.fields.supplier')" :error="err('supplier')" />

                        <div class="grid grid-cols-2 gap-3">
                            <Input v-model="form.amount" type="number" min="0.01" step="0.01" inputmode="decimal" required :label="t('expenses.fields.amount_incl')" :error="err('amount_total')" />
                            <div>
                                <Input
                                    v-model="form.gst"
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    inputmode="decimal"
                                    :label="t('expenses.fields.gst')"
                                    :disabled="!form.includes_gst"
                                    :error="err('gst_amount')"
                                    @input="gstTouched = true"
                                />
                                <button v-if="gstTouched && form.includes_gst" type="button" class="mt-1 text-xs text-ink-500 hover:text-ink-900 hover:underline dark:hover:text-ink-100" @click="resetGst">
                                    {{ t('expenses.gst_reset') }}
                                </button>
                            </div>
                        </div>
                        <label class="flex items-center gap-2 text-sm text-ink-700 dark:text-ink-300">
                            <input v-model="form.includes_gst" type="checkbox" class="h-4 w-4 rounded border-ink-300 accent-ink-900 dark:border-ink-700 dark:accent-ink-100" />
                            {{ t('expenses.fields.includes_gst') }}
                        </label>

                        <div>
                            <p class="mb-1.5 text-sm font-medium text-ink-700 dark:text-ink-300">{{ t('expenses.fields.payment_method') }}</p>
                            <div class="grid grid-cols-2 gap-1 rounded-control bg-ink-100 p-1 sm:grid-cols-4 dark:bg-ink-800" role="radiogroup">
                                <button
                                    v-for="m in paymentMethods"
                                    :key="m"
                                    type="button"
                                    role="radio"
                                    :aria-checked="form.payment_method === m"
                                    class="rounded-control px-2 py-1.5 text-xs transition-colors"
                                    :class="form.payment_method === m
                                        ? 'bg-white text-ink-900 shadow-subtle dark:bg-ink-950 dark:text-ink-50'
                                        : 'text-ink-500 hover:text-ink-900 dark:hover:text-ink-100'"
                                    @click="form.payment_method = m"
                                >
                                    {{ methodLabel(m) }}
                                </button>
                            </div>
                        </div>

                        <Select v-model="form.vehicle_id" :label="t('expenses.fields.vehicle_optional')" :error="err('vehicle_id')">
                            <option value="">{{ t('expenses.no_vehicle') }}</option>
                            <option v-for="v in vehicles" :key="v.id" :value="v.id">{{ vehicleLabel(v) }}</option>
                        </Select>

                        <Textarea v-model="form.notes" :rows="2" :label="t('expenses.fields.notes')" :error="err('notes')" />

                        <div>
                            <p class="mb-1.5 text-sm font-medium text-ink-700 dark:text-ink-300">{{ t('expenses.fields.receipt') }}</p>
                            <div
                                v-if="editing?.has_receipt && !form.receipt && !form.remove_receipt"
                                class="mb-2 flex items-center justify-between rounded-control bg-ink-50 px-3 py-2 text-sm dark:bg-ink-800"
                            >
                                <a :href="`${base}/${editing.id}/receipt`" target="_blank" rel="noopener" class="inline-flex items-center gap-1.5 text-ink-700 hover:underline dark:text-ink-200">
                                    <PaperClipIcon class="h-4 w-4" />{{ t('expenses.current_receipt') }}
                                </a>
                                <button type="button" class="text-xs text-danger-600 hover:underline" @click="form.remove_receipt = true">{{ t('expenses.remove_receipt') }}</button>
                            </div>
                            <input
                                ref="fileInput"
                                type="file"
                                accept=".jpg,.jpeg,.png,.pdf,image/jpeg,image/png,application/pdf"
                                class="block w-full text-sm text-ink-600 file:mr-3 file:rounded-control file:border-0 file:bg-ink-100 file:px-3 file:py-2 file:text-sm file:font-medium file:text-ink-900 hover:file:bg-ink-200 dark:text-ink-300 dark:file:bg-ink-800 dark:file:text-ink-100"
                                @change="pickReceipt"
                            />
                            <p class="mt-1 text-xs text-ink-400">{{ t('expenses.receipt_hint') }}</p>
                            <p v-if="err('receipt') || err('plan_limit')" class="mt-1 text-sm text-danger-600 dark:text-danger-500">{{ err('receipt') || err('plan_limit') }}</p>
                            <div v-if="form.progress" class="mt-2 h-1 overflow-hidden rounded-full bg-ink-100 dark:bg-ink-800">
                                <div class="h-full bg-ink-900 transition-all dark:bg-ink-100" :style="{ width: `${form.progress.percentage}%` }" />
                            </div>
                        </div>

                        <p v-if="err('expense')" class="text-sm text-danger-600 dark:text-danger-500">{{ err('expense') }}</p>
                    </form>

                    <footer class="flex justify-end gap-2 border-t border-ink-200 px-5 py-4 dark:border-ink-800">
                        <Button variant="ghost" @click="panelOpen = false">{{ t('common.cancel') }}</Button>
                        <Button type="submit" form="expense-form" :loading="form.processing">{{ t('common.save') }}</Button>
                    </footer>
                </aside>
            </Transition>
        </Teleport>

        <!-- Void -->
        <Modal :show="!!voiding" :title="t('expenses.void_title')" @close="voiding = null">
            <form id="void-form" class="space-y-3" @submit.prevent="submitVoid">
                <p class="text-sm text-ink-600 dark:text-ink-300">
                    {{ t('expenses.void_body', { description: voiding?.description ?? '', amount: formatAUD(voiding?.amount_total ?? 0) }) }}
                </p>
                <Input v-model="voidForm.reason" required :label="t('expenses.void_reason')" :error="voidForm.errors.reason || voidForm.errors.expense" />
            </form>
            <template #footer>
                <Button variant="ghost" @click="voiding = null">{{ t('common.cancel') }}</Button>
                <Button type="submit" form="void-form" variant="danger" :loading="voidForm.processing">{{ t('expenses.void') }}</Button>
            </template>
        </Modal>

        <!-- Categories -->
        <Modal :show="categoriesOpen" :title="t('expenses.categories')" @close="categoriesOpen = false">
            <ul class="divide-y divide-ink-100 dark:divide-ink-800">
                <li v-for="c in categories" :key="c.id" class="flex items-center gap-2 py-2">
                    <input
                        :value="renaming[c.id] ?? c.name"
                        class="h-9 min-w-0 flex-1 rounded-control border border-transparent bg-transparent px-2 text-sm text-ink-900 transition-colors hover:border-ink-200 focus:border-ink-400 focus:outline-none dark:text-ink-50 dark:hover:border-ink-700"
                        :class="c.is_hidden ? 'text-ink-400 line-through dark:text-ink-500' : ''"
                        :aria-label="t('expenses.category_name')"
                        @input="renaming[c.id] = $event.target.value"
                        @keydown.enter.prevent="saveCategory(c)"
                    />
                    <StatusBadge v-if="c.system_key" variant="neutral" :label="t('expenses.default_category')" />
                    <Button v-if="renaming[c.id] !== undefined && renaming[c.id] !== c.name" size="sm" @click="saveCategory(c)">{{ t('common.save') }}</Button>
                    <Button size="sm" variant="ghost" @click="saveCategory(c, { is_hidden: !c.is_hidden })">
                        {{ c.is_hidden ? t('expenses.show_category') : t('expenses.hide_category') }}
                    </Button>
                </li>
            </ul>
            <form class="mt-4 flex gap-2" @submit.prevent="addCategory">
                <Input v-model="newCategory.name" :placeholder="t('expenses.new_category_placeholder')" class="flex-1" :error="newCategory.errors.name" />
                <Button type="submit" variant="secondary" :loading="newCategory.processing">{{ t('expenses.add_category') }}</Button>
            </form>
            <p class="mt-3 text-xs text-ink-400">{{ t('expenses.categories_hint') }}</p>
        </Modal>
    </AppLayout>
</template>
