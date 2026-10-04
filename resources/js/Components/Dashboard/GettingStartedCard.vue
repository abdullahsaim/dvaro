<script setup>
// First-run "Getting started" checklist on the tenant dashboard. Shown only
// to an admin and only until explicitly dismissed — it does NOT auto-hide
// once every step is done; instead it switches to a completed state so
// finishing the last step reads as "you're all set", not a silent vanish
// (TenantDashboardController::onboardingChecklist / dismissOnboarding).
// Each row links straight to the screen that completes it — no separate
// "setup wizard" to navigate, the real app IS the wizard.
import { computed } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { CheckCircleIcon, XMarkIcon, ArrowRightIcon, SparklesIcon } from '@heroicons/vue/24/outline';
import { CheckCircleIcon as CheckCircleSolid } from '@heroicons/vue/24/solid';

const props = defineProps({
    checklist: { type: Object, required: true },
    base: { type: String, required: true },
});

const { t } = useI18n();

const isComplete = computed(() => props.checklist.completedCount === props.checklist.totalCount);

const progressPct = computed(() =>
    props.checklist.totalCount > 0
        ? Math.round((props.checklist.completedCount / props.checklist.totalCount) * 100)
        : 0,
);

function dismiss() {
    router.post(`${props.base}/dashboard/onboarding/dismiss`, {}, { preserveScroll: true });
}
</script>

<template>
    <section class="relative overflow-hidden rounded-card border border-ink-200 bg-white shadow-subtle dark:border-ink-800 dark:bg-ink-900">
        <button
            type="button"
            class="absolute right-3 top-3 z-10 inline-flex h-7 w-7 items-center justify-center rounded-control text-ink-400 transition-colors hover:bg-ink-100 hover:text-ink-700 dark:hover:bg-ink-800 dark:hover:text-ink-200"
            :aria-label="t('dashboard.getting_started.dismiss')"
            @click="dismiss"
        >
            <XMarkIcon class="h-4 w-4" />
        </button>

        <div class="p-5 pr-12">
            <div class="flex flex-wrap items-baseline justify-between gap-2">
                <div class="flex items-center gap-2">
                    <SparklesIcon v-if="isComplete" class="h-5 w-5 text-success-600" />
                    <div>
                        <h2 class="text-base font-semibold text-ink-900 dark:text-ink-50">
                            {{ isComplete ? t('dashboard.getting_started.title_complete') : t('dashboard.getting_started.title') }}
                        </h2>
                        <p class="mt-0.5 text-sm text-ink-500">
                            {{ isComplete ? t('dashboard.getting_started.subtitle_complete') : t('dashboard.getting_started.subtitle') }}
                        </p>
                    </div>
                </div>
                <span class="shrink-0 text-xs font-medium text-ink-500">
                    {{ t('dashboard.getting_started.progress', { completed: checklist.completedCount, total: checklist.totalCount }) }}
                </span>
            </div>

            <!-- Progress bar -->
            <div class="mt-3 h-1.5 overflow-hidden rounded-full bg-ink-100 dark:bg-ink-800">
                <div
                    class="h-full rounded-full bg-ink-950 transition-all duration-500 dark:bg-ink-50"
                    :style="{ width: `${progressPct}%` }"
                />
            </div>

            <ul class="mt-4 grid gap-2 sm:grid-cols-2">
                <li v-for="item in checklist.items" :key="item.key">
                    <Link
                        v-if="!item.done"
                        :href="`${base}/${item.url}`"
                        class="group flex items-start gap-3 rounded-control border border-ink-200 p-3 transition-colors hover:border-ink-300 hover:bg-ink-50 dark:border-ink-800 dark:hover:border-ink-700 dark:hover:bg-ink-800/60"
                    >
                        <CheckCircleIcon class="mt-0.5 h-5 w-5 shrink-0 text-ink-300 dark:text-ink-700" />
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-medium text-ink-900 dark:text-ink-100">
                                {{ t(`dashboard.getting_started.items.${item.key}.title`) }}
                            </p>
                            <p class="mt-0.5 text-xs text-ink-500">
                                {{ t(`dashboard.getting_started.items.${item.key}.description`) }}
                            </p>
                        </div>
                        <ArrowRightIcon class="mt-1 h-3.5 w-3.5 shrink-0 text-ink-300 transition-transform group-hover:translate-x-0.5 group-hover:text-ink-600 dark:text-ink-600 dark:group-hover:text-ink-300" />
                    </Link>

                    <div
                        v-else
                        class="flex items-start gap-3 rounded-control border border-ink-100 bg-ink-50/60 p-3 dark:border-ink-800 dark:bg-ink-950/40"
                    >
                        <CheckCircleSolid class="mt-0.5 h-5 w-5 shrink-0 text-success-600" />
                        <p class="text-sm font-medium text-ink-500 line-through dark:text-ink-500">
                            {{ t(`dashboard.getting_started.items.${item.key}.title`) }}
                        </p>
                    </div>
                </li>
            </ul>
        </div>
    </section>
</template>
