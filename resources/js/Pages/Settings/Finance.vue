<script setup>
// Settings → Invoicing & late fees. The late-fee rules were already applied by
// the invoice engine; this is the first screen that can change them, so it
// shows a worked example of what the current settings would charge.
import { computed } from 'vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import Button from '@/Components/UI/Button.vue';
import Input from '@/Components/UI/Input.vue';
import Textarea from '@/Components/UI/Textarea.vue';
import { useCurrency } from '@/composables/useCurrency';

const props = defineProps({
    settings: { type: Object, required: true },
    canManage: { type: Boolean, default: false },
});

const { t } = useI18n();
const { formatAUD, toCents } = useCurrency();
const page = usePage();
const base = computed(() => `/app/${page.props.tenant.slug}/settings/finance`);

const form = useForm({
    late_fees_enabled: props.settings.late_fees_enabled,
    late_fee_grace_days: props.settings.late_fee_grace_days,
    late_fee_type: props.settings.late_fee_type,
    late_fee_amount: (props.settings.late_fee_amount / 100).toFixed(2), // AUD in the input
    late_fee_percentage: props.settings.late_fee_percentage,
    invoice_prefix: props.settings.invoice_prefix ?? '',
    invoice_payment_terms_days: props.settings.invoice_payment_terms_days,
    invoice_footer_note: props.settings.invoice_footer_note ?? '',
});

// "A $1,000 invoice 10 days overdue would add $50."
const example = computed(() => {
    if (!form.late_fees_enabled) return t('settings.finance.example_off');

    const sample = 100000; // $1,000
    const fee = form.late_fee_type === 'fixed'
        ? toCents(form.late_fee_amount || 0)
        : Math.round((sample * Number(form.late_fee_percentage || 0)) / 100);

    return t('settings.finance.example', {
        invoice: formatAUD(sample),
        days: Number(form.late_fee_grace_days || 0) + 3,
        grace: form.late_fee_grace_days,
        fee: formatAUD(fee),
    });
});

function submit() {
    form.transform((data) => ({
        ...data,
        late_fees_enabled: data.late_fees_enabled ? 1 : 0,
        late_fee_amount: toCents(data.late_fee_amount || 0),
        late_fee_percentage: Number(data.late_fee_percentage || 0),
        invoice_prefix: data.invoice_prefix || null,
        invoice_footer_note: data.invoice_footer_note || null,
    })).put(base.value, { preserveScroll: true });
}

const card = 'rounded-card border border-ink-200 bg-white p-5 shadow-subtle dark:border-ink-800 dark:bg-ink-900';
</script>

<template>
    <AppLayout>
        <Head :title="t('settings.finance.title')" />

        <PageHeader :title="t('settings.finance.title')" :description="t('settings.finance.intro')">
            <template #actions>
                <Button variant="ghost" @click="router.visit(`/app/${page.props.tenant.slug}/settings`)">
                    {{ t('common.back') }}
                </Button>
            </template>
        </PageHeader>

        <form class="max-w-3xl space-y-6" @submit.prevent="submit">
            <!-- Late fees -->
            <section :class="card">
                <h2 class="text-sm font-semibold text-ink-900 dark:text-ink-50">{{ t('settings.finance.late_fees') }}</h2>
                <p class="mt-1 text-sm text-ink-500">{{ t('settings.finance.late_fees_hint') }}</p>

                <label class="mt-4 flex items-center gap-3">
                    <input
                        v-model="form.late_fees_enabled"
                        type="checkbox"
                        :disabled="!canManage"
                        class="h-4 w-4 rounded border-ink-300 accent-ink-900 dark:border-ink-700 dark:accent-ink-100"
                    />
                    <span class="text-sm text-ink-700 dark:text-ink-300">{{ t('settings.finance.late_fees_enabled') }}</span>
                </label>

                <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-3" :class="form.late_fees_enabled ? '' : 'pointer-events-none opacity-50'">
                    <Input
                        v-model="form.late_fee_grace_days"
                        type="number"
                        min="0"
                        max="90"
                        :disabled="!canManage"
                        :label="t('settings.finance.grace_days')"
                        :help="t('settings.finance.grace_days_hint')"
                        :error="form.errors.late_fee_grace_days"
                    />

                    <div class="sm:col-span-2">
                        <p class="mb-1.5 text-sm font-medium text-ink-700 dark:text-ink-300">{{ t('settings.finance.fee_type') }}</p>
                        <div class="grid grid-cols-2 gap-1 rounded-control bg-ink-100 p-1 dark:bg-ink-800" role="radiogroup">
                            <button
                                v-for="type in ['fixed', 'percentage']"
                                :key="type"
                                type="button"
                                role="radio"
                                :aria-checked="form.late_fee_type === type"
                                :disabled="!canManage"
                                class="rounded-control px-3 py-1.5 text-sm transition-colors"
                                :class="form.late_fee_type === type
                                    ? 'bg-white text-ink-900 shadow-subtle dark:bg-ink-950 dark:text-ink-50'
                                    : 'text-ink-500 hover:text-ink-900 dark:hover:text-ink-100'"
                                @click="form.late_fee_type = type"
                            >
                                {{ t(`settings.finance.type_${type}`) }}
                            </button>
                        </div>
                    </div>

                    <Input
                        v-if="form.late_fee_type === 'fixed'"
                        v-model="form.late_fee_amount"
                        type="number"
                        min="0"
                        step="0.01"
                        :disabled="!canManage"
                        :label="t('settings.finance.fee_amount')"
                        :error="form.errors.late_fee_amount"
                    />
                    <Input
                        v-else
                        v-model="form.late_fee_percentage"
                        type="number"
                        min="0"
                        max="100"
                        :disabled="!canManage"
                        :label="t('settings.finance.fee_percentage')"
                        :error="form.errors.late_fee_percentage"
                    />
                </div>

                <p class="mt-4 rounded-control bg-ink-50 px-3 py-2 text-sm text-ink-600 dark:bg-ink-950 dark:text-ink-300">
                    {{ example }}
                </p>
            </section>

            <!-- Invoicing -->
            <section :class="card">
                <h2 class="text-sm font-semibold text-ink-900 dark:text-ink-50">{{ t('settings.finance.invoicing') }}</h2>
                <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <Input
                        v-model="form.invoice_prefix"
                        :disabled="!canManage"
                        :label="t('settings.finance.invoice_prefix')"
                        :help="t('settings.finance.invoice_prefix_hint')"
                        :error="form.errors.invoice_prefix"
                    />
                    <Input
                        v-model="form.invoice_payment_terms_days"
                        type="number"
                        min="0"
                        max="180"
                        :disabled="!canManage"
                        :label="t('settings.finance.payment_terms')"
                        :help="t('settings.finance.payment_terms_hint')"
                        :error="form.errors.invoice_payment_terms_days"
                    />
                    <div class="sm:col-span-2">
                        <Textarea
                            v-model="form.invoice_footer_note"
                            :rows="2"
                            :disabled="!canManage"
                            :label="t('settings.finance.footer_note')"
                            :placeholder="t('settings.finance.footer_note_placeholder')"
                            :error="form.errors.invoice_footer_note"
                        />
                    </div>
                </div>
            </section>

            <div v-if="canManage" class="flex justify-end">
                <Button type="submit" :loading="form.processing">{{ t('common.save') }}</Button>
            </div>
            <p v-else class="text-sm text-ink-500">{{ t('settings.finance.admin_only') }}</p>
        </form>
    </AppLayout>
</template>
