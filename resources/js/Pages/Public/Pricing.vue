<script setup>
// Public pricing page. Shows every ACTIVE plan (inactive plans are filtered out
// server-side) with a monthly/annual toggle. Pricing is always live plan data.
// FAQ + CTA copy comes from the CMS.
import { computed, ref } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import PlanCard from '@/Components/Public/PlanCard.vue';
import FaqAccordion from '@/Components/Public/FaqAccordion.vue';
import { vReveal } from '@/directives/reveal.js';
import { useSeo } from '@/composables/useSeo.js';

const props = defineProps({
    plans: { type: Array, default: () => [] },
    faq: { type: Object, default: () => ({}) },
    cta: { type: Object, default: () => ({}) },
});

const { t } = useI18n();

const seo = useSeo({
    title: t('public.pricing.title'),
    description: t('public.pricing.subtitle'),
});

const cycle = ref('monthly');

// Highlight the middle-priced PAID plan as "most popular" (free/freemium plans
// are never featured). Falls back to no highlight when there are no paid plans.
const featuredId = computed(() => {
    const paid = props.plans
        .filter((p) => !p.is_free && p.price_monthly > 0)
        .sort((a, b) => a.price_monthly - b.price_monthly);
    if (!paid.length) return null;
    return paid[Math.floor((paid.length - 1) / 2)].id;
});

// Biggest annual discount across paid plans, as a percentage for the toggle
// pill ("Save up to X%"). 0 hides the pill.
const maxSavingPercent = computed(() => {
    let best = 0;
    for (const plan of props.plans) {
        const monthly = plan.price_monthly ?? 0;
        const annual = plan.price_annual ?? 0;
        if (monthly > 0 && annual > 0 && annual < monthly * 12) {
            best = Math.max(best, Math.round((1 - annual / (monthly * 12)) * 100));
        }
    }
    return best;
});

// FAQ entries from the flat CMS map (faq_{i}_question/answer).
const faqItems = computed(() =>
    [1, 2, 3, 4, 5, 6]
        .map((i) => ({
            question: props.faq[`faq_${i}_question`],
            answer: props.faq[`faq_${i}_answer`],
        }))
        .filter((f) => f.question && f.answer),
);
</script>

<template>
    <PublicLayout>
        <Head :title="seo.title">
            <meta v-for="m in seo.meta" :key="m.key" :head-key="m.key" :name="m.name" :property="m.property" :content="m.content" />
        </Head>

        <section class="relative overflow-hidden">
            <div class="pointer-events-none absolute inset-0" aria-hidden="true">
                <div class="absolute -top-24 left-1/2 h-72 w-[46rem] -translate-x-1/2 rounded-full bg-ink-100/80 blur-3xl dark:bg-ink-800/30"></div>
            </div>

            <div class="relative mx-auto w-full max-w-7xl px-4 py-20 sm:px-6 lg:px-8">
                <div v-reveal class="mx-auto max-w-2xl text-center">
                    <p class="text-xs font-semibold uppercase tracking-widest text-ink-400">{{ t('public.pricing.kicker') }}</p>
                    <h1 class="mt-3 text-4xl font-bold tracking-tight text-ink-900 dark:text-ink-50 sm:text-5xl">{{ t('public.pricing.title') }}</h1>
                    <p class="mt-4 text-lg text-ink-600 dark:text-ink-300">{{ t('public.pricing.subtitle') }}</p>
                </div>

                <!-- Monthly / Annual toggle -->
                <div v-reveal="80" class="mt-10 flex justify-center">
                    <div class="inline-flex items-center rounded-control border border-ink-200 bg-white p-1 shadow-subtle dark:border-ink-800 dark:bg-ink-900">
                        <button
                            type="button"
                            class="rounded-md px-4 py-1.5 text-sm font-medium transition"
                            :class="cycle === 'monthly' ? 'bg-ink-950 text-white shadow-subtle dark:bg-ink-50 dark:text-ink-950' : 'text-ink-600 hover:text-ink-900 dark:text-ink-300 dark:hover:text-white'"
                            @click="cycle = 'monthly'"
                        >
                            {{ t('public.pricing.monthly') }}
                        </button>
                        <button
                            type="button"
                            class="inline-flex items-center gap-2 rounded-md px-4 py-1.5 text-sm font-medium transition"
                            :class="cycle === 'annual' ? 'bg-ink-950 text-white shadow-subtle dark:bg-ink-50 dark:text-ink-950' : 'text-ink-600 hover:text-ink-900 dark:text-ink-300 dark:hover:text-white'"
                            @click="cycle = 'annual'"
                        >
                            {{ t('public.pricing.annual') }}
                            <span
                                v-if="maxSavingPercent > 0"
                                class="rounded-full px-1.5 py-0.5 text-[0.65rem] font-semibold"
                                :class="cycle === 'annual'
                                    ? 'bg-white/20 text-white dark:bg-ink-950/10 dark:text-ink-950'
                                    : 'bg-success-50 text-success-700 dark:bg-success-900/40 dark:text-success-500'"
                            >
                                {{ t('public.pricing.save_up_to', { percent: maxSavingPercent }) }}
                            </span>
                        </button>
                    </div>
                </div>

                <p v-if="!plans.length" class="mt-12 text-center text-ink-500">
                    {{ t('public.pricing.no_plans') }}
                </p>

                <div v-else class="mx-auto mt-14 grid max-w-5xl gap-6 md:grid-cols-2 lg:grid-cols-3">
                    <div v-for="(plan, i) in plans" :key="plan.id" v-reveal="i * 100">
                        <PlanCard :plan="plan" :cycle="cycle" :featured="plan.id === featuredId" class="h-full" />
                    </div>
                </div>

                <!-- Trust line -->
                <div v-reveal class="mx-auto mt-10 flex max-w-3xl flex-wrap items-center justify-center gap-x-8 gap-y-3 text-sm text-ink-500 dark:text-ink-400">
                    <span class="inline-flex items-center gap-2">
                        <svg class="h-4 w-4 text-success-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg>
                        {{ t('public.pricing.trust_cancel') }}
                    </span>
                    <span class="inline-flex items-center gap-2">
                        <svg class="h-4 w-4 text-success-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg>
                        {{ t('public.pricing.trust_aud') }}
                    </span>
                    <span class="inline-flex items-center gap-2">
                        <svg class="h-4 w-4 text-success-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg>
                        {{ t('public.pricing.trust_data') }}
                    </span>
                </div>
            </div>
        </section>

        <!-- Pricing FAQ -->
        <section v-if="faqItems.length" class="border-t border-ink-200 bg-ink-50 dark:border-ink-800 dark:bg-ink-900/40">
            <div class="mx-auto w-full max-w-3xl px-4 py-20 sm:px-6 lg:px-8">
                <div v-reveal class="text-center">
                    <h2 class="text-3xl font-bold tracking-tight text-ink-900 dark:text-ink-50">{{ t('public.faq.title') }}</h2>
                    <p class="mt-3 text-ink-600 dark:text-ink-300">{{ t('public.faq.subtitle') }}</p>
                </div>
                <div v-reveal="120" class="mt-10">
                    <FaqAccordion :items="faqItems" />
                </div>
            </div>
        </section>

        <!-- Custom plan / contact CTA -->
        <section class="border-t border-ink-200 dark:border-ink-800">
            <div v-reveal class="mx-auto w-full max-w-3xl px-4 py-16 text-center sm:px-6 lg:px-8">
                <h2 class="text-2xl font-bold tracking-tight text-ink-900 dark:text-ink-50">{{ t('public.pricing.custom_title') }}</h2>
                <p class="mt-3 text-ink-600 dark:text-ink-300">{{ t('public.pricing.custom_subtitle') }}</p>
                <Link href="/contact" class="mt-7 inline-block rounded-control border border-ink-300 px-6 py-3 font-semibold text-ink-900 transition hover:bg-ink-100 dark:border-ink-700 dark:text-white dark:hover:bg-ink-800">
                    {{ t('public.nav.contact') }}
                </Link>
            </div>
        </section>
    </PublicLayout>
</template>
