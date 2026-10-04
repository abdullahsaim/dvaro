<script setup>
// Tenant self-registration — a focused 3-step wizard (Account → Company →
// Plan), deliberately NOT wrapped in the marketing PublicLayout: a signup
// funnel should have nothing to click away to. Each step validates locally
// before advancing; the server still re-validates everything on final submit
// (the authority), and a server-side error jumps back to whichever step owns
// that field rather than failing silently on step 3.
import { computed, ref, watch } from 'vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import Button from '@/Components/UI/Button.vue';
import Input from '@/Components/UI/Input.vue';
import { SunIcon, MoonIcon, CheckIcon } from '@heroicons/vue/24/outline';
import { useColorMode } from '@/composables/useColorMode';
import { useCurrency } from '@/composables/useCurrency';
import { cmsImage } from '@/cms/defaultImages.js';

const props = defineProps({
    plans: { type: Array, default: () => [] },
});

const { t } = useI18n();
const page = usePage();
const { isDark, toggle: toggleColorMode } = useColorMode();
const { formatAUD } = useCurrency();

const branding = computed(() => page.props.branding ?? {});
const logoLight = computed(
    () => cmsImage('logo_light', branding.value.logo_light) || cmsImage('logo_dark', branding.value.logo_dark),
);
const logoDark = computed(
    () => cmsImage('logo_dark', branding.value.logo_dark) || cmsImage('logo_light', branding.value.logo_light),
);

// ── Wizard state ─────────────────────────────────────────────────────────────
const STEPS = ['account', 'company', 'plan'];
const step = ref(0);
const totalSteps = STEPS.length;

const fieldsByStep = {
    account: ['admin_name', 'email', 'password', 'password_confirmation'],
    company: ['company_name'],
    plan: ['plan_id'],
};

const form = useForm({
    admin_name: '',
    email: '',
    password: '',
    password_confirmation: '',
    company_name: '',
    // Pre-select the cheapest/free plan so a user who never touches step 3
    // still submits something valid.
    plan_id: props.plans[0]?.id ?? null,
});

// Per-step client-side "is this step fillable enough to continue" — real
// validation (format, uniqueness, password rules) stays server-side; this
// only blocks an obviously-empty field from advancing.
const stepValid = computed(() => {
    if (STEPS[step.value] === 'account') {
        return (
            form.admin_name.trim().length > 0
            && form.email.trim().length > 0
            && form.password.length >= 8
            && form.password === form.password_confirmation
        );
    }
    if (STEPS[step.value] === 'company') {
        return form.company_name.trim().length > 0;
    }
    return true;
});

function next() {
    if (!stepValid.value || step.value >= totalSteps - 1) return;
    form.clearErrors(...fieldsByStep[STEPS[step.value]]);
    step.value += 1;
}

function back() {
    if (step.value === 0) return;
    step.value -= 1;
}

function submit() {
    if (step.value < totalSteps - 1) {
        next();
        return;
    }

    form.post('/register', {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
}

// A server-side validation failure can belong to an earlier step (e.g. the
// email is already taken) — jump back to wherever that field actually lives
// so the error is never stranded off-screen on step 3.
watch(
    () => form.errors,
    (errors) => {
        const erroredFields = Object.keys(errors);
        if (erroredFields.length === 0) return;

        const earliestStep = STEPS.findIndex((s) => fieldsByStep[s].some((f) => erroredFields.includes(f)));
        if (earliestStep !== -1 && earliestStep < step.value) {
            step.value = earliestStep;
        }
    },
    { deep: true },
);

// ── Company slug preview (cosmetic only — the server computes the real one
// via Str::slug, including collision-safe suffixing if ever added) ─────────
const slugPreview = computed(() => {
    const slug = form.company_name
        .toLowerCase()
        .trim()
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '');
    return slug || 'your-company';
});

// ── Plan step helpers ────────────────────────────────────────────────────────
const limitLines = (plan) => {
    const limits = plan.limits ?? {};
    return [
        { key: 'max_vehicles', label: 'public.pricing.limits.vehicles' },
        { key: 'max_staff_users', label: 'public.pricing.limits.staff' },
        { key: 'max_customers', label: 'public.pricing.limits.customers' },
    ]
        .filter((l) => Number(limits[l.key]) > 0)
        .map((l) => t(l.label, { count: Number(limits[l.key]).toLocaleString('en-AU') }));
};

const iconBtn =
    'inline-flex h-9 w-9 items-center justify-center rounded-control text-ink-500 transition-colors hover:bg-ink-100 hover:text-ink-900 dark:hover:bg-ink-800 dark:hover:text-ink-100';
</script>

<template>
    <Head :title="t('auth.register')" />

    <div class="flex min-h-screen flex-col bg-white text-ink-900 transition-colors dark:bg-ink-950 dark:text-ink-100">
        <!-- Minimal top bar — no marketing nav, nothing to click away to -->
        <header class="flex h-16 items-center justify-between px-4 sm:px-6 lg:px-8">
            <Link href="/" class="flex items-center">
                <template v-if="logoLight || logoDark">
                    <img :src="logoLight" :alt="t('app.name')" class="h-7 w-auto max-w-40 object-contain object-left dark:hidden" />
                    <img :src="logoDark" :alt="t('app.name')" class="hidden h-7 w-auto max-w-40 object-contain object-left dark:block" />
                </template>
                <template v-else>
                    <span class="grid h-8 w-8 place-items-center rounded-control bg-ink-950 text-sm font-bold text-white dark:bg-ink-50 dark:text-ink-950">D</span>
                    <span class="ml-2 text-lg font-semibold tracking-tight">{{ t('app.name') }}</span>
                </template>
            </Link>

            <div class="flex items-center gap-3">
                <button type="button" :class="iconBtn" :aria-label="t('common.toggle_theme')" @click="toggleColorMode">
                    <SunIcon v-if="isDark" class="h-5 w-5" />
                    <MoonIcon v-else class="h-5 w-5" />
                </button>
                <span class="hidden text-sm text-ink-500 sm:inline">{{ t('auth.have_account') }}</span>
                <Link href="/find-workspace" class="text-sm font-semibold text-ink-900 hover:underline dark:text-ink-100">
                    {{ t('auth.sign_in') }}
                </Link>
            </div>
        </header>

        <!-- Progress -->
        <div class="mx-auto w-full max-w-2xl px-4 pt-4 sm:px-6">
            <div class="flex items-center gap-2">
                <template v-for="(s, i) in STEPS" :key="s">
                    <div class="flex flex-1 items-center gap-2">
                        <div
                            class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full text-xs font-semibold transition-colors"
                            :class="i < step
                                ? 'bg-ink-950 text-white dark:bg-ink-50 dark:text-ink-950'
                                : i === step
                                    ? 'border-2 border-ink-950 text-ink-950 dark:border-ink-50 dark:text-ink-50'
                                    : 'border border-ink-200 text-ink-400 dark:border-ink-800 dark:text-ink-600'"
                        >
                            <CheckIcon v-if="i < step" class="h-4 w-4" />
                            <span v-else>{{ i + 1 }}</span>
                        </div>
                        <span
                            class="hidden text-sm font-medium sm:inline"
                            :class="i <= step ? 'text-ink-900 dark:text-ink-100' : 'text-ink-400 dark:text-ink-600'"
                        >
                            {{ t(`auth.onboarding.steps.${s}`) }}
                        </span>
                    </div>
                    <div
                        v-if="i < STEPS.length - 1"
                        class="h-px flex-1 transition-colors"
                        :class="i < step ? 'bg-ink-950 dark:bg-ink-50' : 'bg-ink-200 dark:bg-ink-800'"
                    />
                </template>
            </div>
            <p class="mt-2 text-xs text-ink-400 sm:hidden">{{ t('auth.onboarding.step_label', { current: step + 1, total: totalSteps }) }}</p>
        </div>

        <!-- Step content -->
        <main class="flex flex-1 items-start justify-center px-4 py-8 sm:px-6">
            <div class="w-full" :class="STEPS[step] === 'plan' ? 'max-w-3xl' : 'max-w-md'">
                <form @submit.prevent="submit">
                    <Transition name="step-swap">
                        <!-- Step 1: Account -->
                        <div v-if="STEPS[step] === 'account'" key="account">
                            <h1 class="text-2xl font-semibold text-ink-900 dark:text-ink-50">{{ t('auth.onboarding.account_title') }}</h1>
                            <p class="mt-1.5 text-sm text-ink-500">{{ t('auth.onboarding.account_subtitle') }}</p>

                            <div class="mt-6 space-y-4 rounded-card border border-ink-200 bg-white p-6 shadow-subtle dark:border-ink-800 dark:bg-ink-900">
                                <Input
                                    v-model="form.admin_name"
                                    autocomplete="name"
                                    autofocus
                                    required
                                    :label="t('auth.onboarding.your_name')"
                                    :placeholder="t('auth.onboarding.your_name_placeholder')"
                                    :error="form.errors.admin_name"
                                />
                                <Input
                                    v-model="form.email"
                                    type="email"
                                    autocomplete="username"
                                    required
                                    :label="t('auth.onboarding.work_email')"
                                    :placeholder="t('auth.onboarding.work_email_placeholder')"
                                    :error="form.errors.email"
                                />
                                <Input
                                    v-model="form.password"
                                    type="password"
                                    autocomplete="new-password"
                                    required
                                    :label="t('auth.password')"
                                    :help="t('auth.onboarding.password_hint')"
                                    :error="form.errors.password"
                                />
                                <Input
                                    v-model="form.password_confirmation"
                                    type="password"
                                    autocomplete="new-password"
                                    required
                                    :label="t('auth.confirm_password')"
                                />
                            </div>
                        </div>

                        <!-- Step 2: Company -->
                        <div v-else-if="STEPS[step] === 'company'" key="company">
                            <h1 class="text-2xl font-semibold text-ink-900 dark:text-ink-50">{{ t('auth.onboarding.company_title') }}</h1>
                            <p class="mt-1.5 text-sm text-ink-500">{{ t('auth.onboarding.company_subtitle') }}</p>

                            <div class="mt-6 space-y-4 rounded-card border border-ink-200 bg-white p-6 shadow-subtle dark:border-ink-800 dark:bg-ink-900">
                                <Input
                                    v-model="form.company_name"
                                    autocomplete="organization"
                                    autofocus
                                    required
                                    :label="t('auth.company_name')"
                                    :placeholder="t('auth.onboarding.company_name_placeholder')"
                                    :error="form.errors.company_name"
                                />
                                <p class="text-xs text-ink-400">
                                    {{ t('auth.onboarding.workspace_preview') }}
                                    <span class="font-medium text-ink-600 dark:text-ink-300">dvaro.com.au/app/{{ slugPreview }}</span>
                                </p>
                            </div>
                        </div>

                        <!-- Step 3: Plan -->
                        <div v-else key="plan">
                            <h1 class="text-2xl font-semibold text-ink-900 dark:text-ink-50">{{ t('auth.onboarding.plan_title') }}</h1>
                            <p class="mt-1.5 text-sm text-ink-500">{{ t('auth.onboarding.plan_subtitle') }}</p>

                            <div class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                                <button
                                    v-for="plan in plans"
                                    :key="plan.id"
                                    type="button"
                                    class="relative flex flex-col rounded-card border p-5 text-left shadow-subtle transition hover:shadow-pop"
                                    :class="form.plan_id === plan.id
                                        ? 'border-ink-950 ring-1 ring-ink-950 bg-ink-50 dark:border-ink-100 dark:ring-ink-100 dark:bg-ink-900'
                                        : 'border-ink-200 bg-white dark:border-ink-800 dark:bg-ink-900'"
                                    @click="form.plan_id = plan.id"
                                >
                                    <span
                                        v-if="form.plan_id === plan.id"
                                        class="absolute right-3 top-3 flex h-5 w-5 items-center justify-center rounded-full bg-ink-950 text-white dark:bg-ink-50 dark:text-ink-950"
                                    >
                                        <CheckIcon class="h-3.5 w-3.5" />
                                    </span>

                                    <h3 class="text-base font-semibold text-ink-900 dark:text-ink-50">{{ plan.name }}</h3>
                                    <p v-if="plan.description" class="mt-1 text-xs text-ink-500">{{ plan.description }}</p>

                                    <div class="mt-3 flex items-baseline gap-1">
                                        <span v-if="plan.is_free || !plan.price_monthly" class="text-2xl font-bold text-ink-900 dark:text-ink-50">
                                            {{ t('public.pricing.free') }}
                                        </span>
                                        <template v-else>
                                            <span class="text-2xl font-bold tabular-nums text-ink-900 dark:text-ink-50">{{ formatAUD(plan.price_monthly) }}</span>
                                            <span class="text-xs text-ink-500">{{ t('public.pricing.per_month') }}</span>
                                        </template>
                                    </div>

                                    <p v-if="plan.trial_days > 0" class="mt-1 text-xs font-medium text-success-600 dark:text-success-500">
                                        {{ t('public.pricing.trial_days', { days: plan.trial_days }) }}
                                    </p>

                                    <ul class="mt-3 space-y-1">
                                        <li v-for="line in limitLines(plan)" :key="line" class="flex items-center gap-1.5 text-xs text-ink-600 dark:text-ink-300">
                                            <CheckIcon class="h-3.5 w-3.5 shrink-0 text-ink-400" />
                                            {{ line }}
                                        </li>
                                    </ul>
                                </button>
                            </div>
                            <p v-if="form.errors.plan_id" class="mt-2 text-sm text-danger-600 dark:text-danger-500">{{ form.errors.plan_id }}</p>
                        </div>
                    </Transition>

                    <!-- Nav -->
                    <div class="mt-6 flex items-center justify-between">
                        <Button v-if="step > 0" type="button" variant="ghost" @click="back">{{ t('auth.onboarding.back') }}</Button>
                        <span v-else />

                        <Button
                            type="submit"
                            :disabled="!stepValid"
                            :loading="form.processing"
                        >
                            {{ step < totalSteps - 1
                                ? t('auth.onboarding.continue')
                                : (form.processing ? t('auth.onboarding.creating_account') : t('auth.onboarding.create_account_cta')) }}
                        </Button>
                    </div>
                </form>
            </div>
        </main>
    </div>
</template>

<style scoped>
.step-swap-enter-active,
.step-swap-leave-active {
    transition: opacity 0.18s ease, transform 0.18s ease;
}
.step-swap-enter-from {
    opacity: 0;
    transform: translateX(12px);
}
.step-swap-leave-to {
    opacity: 0;
    transform: translateX(-12px);
}
@media (prefers-reduced-motion: reduce) {
    .step-swap-enter-active,
    .step-swap-leave-active {
        transition: none;
    }
}
</style>
