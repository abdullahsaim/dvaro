<script setup>
// Public homepage. All copy comes from the CMS (passed as section maps); the
// pricing preview is live plan data. Scroll reveals via the v-reveal directive
// (IntersectionObserver, reduced-motion aware) — no animation libraries.
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import DemoRequestForm from '@/Components/Public/DemoRequestForm.vue';
import PlanCard from '@/Components/Public/PlanCard.vue';
import StatCounter from '@/Components/Public/StatCounter.vue';
import FaqAccordion from '@/Components/Public/FaqAccordion.vue';
import { vReveal } from '@/directives/reveal.js';
import { useSeo } from '@/composables/useSeo.js';
import { cmsImage } from '@/cms/defaultImages.js';

const props = defineProps({
    hero: { type: Object, default: () => ({}) },
    stats: { type: Object, default: () => ({}) },
    features: { type: Object, default: () => ({}) },
    howItWorks: { type: Object, default: () => ({}) },
    about: { type: Object, default: () => ({}) },
    testimonials: { type: Object, default: () => ({}) },
    faq: { type: Object, default: () => ({}) },
    cta: { type: Object, default: () => ({}) },
    contact: { type: Object, default: () => ({}) },
    plans: { type: Array, default: () => [] },
});

const { t } = useI18n();

const seo = useSeo({
    title: props.hero.hero_heading ?? 'DVARO',
    description: props.hero.hero_subheading ?? '',
});

// Resolve the hero photo — CMS upload if set, else the bundled default photo.
const heroImage = computed(() => cmsImage('hero_image', props.hero.hero_image));

// The about-section illustration — CMS upload if set, else the bundled default.
const aboutImage = computed(() => cmsImage('about_image', props.about.about_image));

// Stats band from the flat CMS map (stat_{i}_value/label).
const statItems = computed(() =>
    [1, 2, 3, 4]
        .map((i) => ({
            value: props.stats[`stat_${i}_value`],
            label: props.stats[`stat_${i}_label`],
        }))
        .filter((s) => s.value),
);

// Build the 6 feature cards from the flat CMS map
// (feature_{i}_title/description/image). Each card's glyph is the CMS-uploaded
// image when set, otherwise the bundled monochrome default.
const featureCards = computed(() =>
    [1, 2, 3, 4, 5, 6]
        .map((i) => ({
            title: props.features[`feature_${i}_title`],
            description: props.features[`feature_${i}_description`],
            image: cmsImage(`feature_${i}_image`, props.features[`feature_${i}_image`]),
        }))
        .filter((f) => f.title),
);

// How-it-works steps from the flat CMS map (how_{i}_title/description).
const steps = computed(() =>
    [1, 2, 3, 4]
        .map((i) => ({
            title: props.howItWorks[`how_${i}_title`],
            description: props.howItWorks[`how_${i}_description`],
        }))
        .filter((s) => s.title),
);

// Build testimonials from the flat CMS map (testimonial_{i}_quote/author).
const testimonialCards = computed(() =>
    [1, 2, 3]
        .map((i) => ({
            quote: props.testimonials[`testimonial_${i}_quote`],
            author: props.testimonials[`testimonial_${i}_author`],
        }))
        .filter((tm) => tm.quote),
);

// FAQ entries from the flat CMS map (faq_{i}_question/answer).
const faqItems = computed(() =>
    [1, 2, 3, 4, 5, 6]
        .map((i) => ({
            question: props.faq[`faq_${i}_question`],
            answer: props.faq[`faq_${i}_answer`],
        }))
        .filter((f) => f.question && f.answer),
);

const previewPlans = computed(() => props.plans.slice(0, 3));
</script>

<template>
    <PublicLayout>
        <Head :title="seo.title">
            <meta v-for="m in seo.meta" :key="m.key" :head-key="m.key" :name="m.name" :property="m.property" :content="m.content" />
        </Head>

        <!-- Hero -->
        <section class="relative overflow-hidden">
            <!-- Decorative background: soft radial glow + dot grid, pure CSS -->
            <div class="pointer-events-none absolute inset-0" aria-hidden="true">
                <div class="absolute -top-32 left-1/2 h-96 w-[52rem] -translate-x-1/2 rounded-full bg-ink-100/80 blur-3xl dark:bg-ink-800/30"></div>
                <div class="absolute inset-0 bg-[radial-gradient(circle,rgb(0_0_0/0.05)_1px,transparent_1px)] [background-size:28px_28px] [mask-image:linear-gradient(to_bottom,black,transparent_70%)] dark:bg-[radial-gradient(circle,rgb(255_255_255/0.06)_1px,transparent_1px)]"></div>
            </div>

            <div class="relative mx-auto grid w-full max-w-7xl items-center gap-12 px-4 py-20 sm:px-6 lg:grid-cols-2 lg:px-8 lg:py-28">
                <div v-reveal>
                    <p v-if="hero.hero_badge" class="inline-flex items-center gap-2 rounded-full border border-ink-200 bg-white/70 px-3.5 py-1.5 text-xs font-medium text-ink-600 shadow-subtle backdrop-blur dark:border-ink-700 dark:bg-ink-900/70 dark:text-ink-300">
                        <span class="relative flex h-2 w-2">
                            <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-success-500 opacity-60 motion-reduce:hidden"></span>
                            <span class="relative inline-flex h-2 w-2 rounded-full bg-success-500"></span>
                        </span>
                        {{ hero.hero_badge }}
                    </p>
                    <h1 class="mt-5 text-4xl font-bold tracking-tight text-ink-900 dark:text-ink-50 sm:text-5xl lg:text-[3.4rem] lg:leading-[1.1]">
                        {{ hero.hero_heading }}
                    </h1>
                    <p class="mt-5 max-w-xl text-lg leading-relaxed text-ink-600 dark:text-ink-300">{{ hero.hero_subheading }}</p>
                    <div class="mt-8 flex flex-wrap gap-3">
                        <Link href="/register" class="group inline-flex items-center gap-2 rounded-control bg-ink-950 px-6 py-3 font-semibold text-white shadow-subtle transition hover:-translate-y-0.5 hover:bg-ink-800 hover:shadow-pop dark:bg-ink-50 dark:text-ink-950 dark:hover:bg-ink-200 motion-reduce:hover:translate-y-0">
                            {{ hero.hero_cta_text || t('public.nav.register') }}
                            <svg class="h-4 w-4 transition-transform group-hover:translate-x-0.5 motion-reduce:group-hover:translate-x-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12l-7.5 7.5M21 12H3" />
                            </svg>
                        </Link>
                        <Link href="/pricing" class="rounded-control border border-ink-300 bg-white/60 px-6 py-3 font-semibold text-ink-900 backdrop-blur transition hover:-translate-y-0.5 hover:bg-ink-100 dark:border-ink-700 dark:bg-ink-900/60 dark:text-white dark:hover:bg-ink-800 motion-reduce:hover:translate-y-0">
                            {{ t('public.pricing.view_all') }}
                        </Link>
                    </div>
                    <p class="mt-6 flex items-center gap-2 text-sm text-ink-500 dark:text-ink-400">
                        <svg class="h-4 w-4 text-success-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                        </svg>
                        {{ t('public.hero.no_card_required') }}
                    </p>
                </div>

                <div v-reveal="150" class="relative">
                    <img v-if="heroImage" :src="heroImage" alt="" class="aspect-[4/3] w-full rounded-card border border-ink-200 object-cover shadow-pop dark:border-ink-800" />
                    <div v-else class="aspect-[4/3] w-full rounded-card bg-gradient-to-br from-ink-700 to-ink-950 dark:from-ink-800 dark:to-ink-950"></div>

                    <!-- Floating "payment received" toast mock — decorative -->
                    <div class="absolute -bottom-5 -left-4 hidden items-center gap-3 rounded-card border border-ink-200 bg-white/95 px-4 py-3 shadow-pop backdrop-blur dark:border-ink-700 dark:bg-ink-900/95 sm:flex" aria-hidden="true">
                        <span class="grid h-9 w-9 place-items-center rounded-full bg-success-50 text-success-600 dark:bg-success-900/40 dark:text-success-500">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m5 13 4 4L19 7" />
                            </svg>
                        </span>
                        <span>
                            <span class="block text-xs font-semibold text-ink-900 dark:text-ink-50">{{ t('public.hero.toast_title') }}</span>
                            <span class="block text-xs text-ink-500">{{ t('public.hero.toast_body') }}</span>
                        </span>
                    </div>
                </div>
            </div>
        </section>

        <!-- Stats band -->
        <section v-if="statItems.length" class="border-t border-ink-200 dark:border-ink-800">
            <div v-reveal class="mx-auto grid w-full max-w-7xl grid-cols-2 gap-x-6 gap-y-10 px-4 py-14 sm:px-6 lg:grid-cols-4 lg:px-8">
                <StatCounter v-for="(stat, i) in statItems" :key="i" :value="stat.value" :label="stat.label" />
            </div>
        </section>

        <!-- Features -->
        <section class="border-t border-ink-200 bg-ink-50 dark:border-ink-800 dark:bg-ink-900/40">
            <div class="mx-auto w-full max-w-7xl px-4 py-20 sm:px-6 lg:px-8">
                <div v-reveal class="mx-auto max-w-2xl text-center">
                    <p class="text-xs font-semibold uppercase tracking-widest text-ink-400">{{ t('public.features.kicker') }}</p>
                    <h2 class="mt-3 text-3xl font-bold tracking-tight text-ink-900 dark:text-ink-50">{{ t('public.features.title') }}</h2>
                    <p class="mt-3 text-ink-600 dark:text-ink-300">{{ t('public.features.subtitle') }}</p>
                </div>
                <div class="mt-12 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                    <div
                        v-for="(feature, i) in featureCards"
                        :key="i"
                        v-reveal="i * 80"
                        class="group rounded-card border border-ink-200 bg-white p-6 shadow-subtle transition duration-300 hover:-translate-y-1 hover:shadow-pop dark:border-ink-800 dark:bg-ink-900 motion-reduce:hover:translate-y-0"
                    >
                        <div v-if="feature.image" class="mb-4 inline-flex h-12 w-12 items-center justify-center rounded-control border border-ink-200 bg-ink-50 transition-colors group-hover:border-ink-300 dark:border-ink-700 dark:bg-ink-800 dark:group-hover:border-ink-600">
                            <img :src="feature.image" alt="" class="h-7 w-7" />
                        </div>
                        <h3 class="text-lg font-semibold text-ink-900 dark:text-ink-50">{{ feature.title }}</h3>
                        <p class="mt-2 text-sm leading-relaxed text-ink-600 dark:text-ink-300">{{ feature.description }}</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- How it works -->
        <section v-if="steps.length" class="border-t border-ink-200 dark:border-ink-800">
            <div class="mx-auto w-full max-w-7xl px-4 py-20 sm:px-6 lg:px-8">
                <div v-reveal class="mx-auto max-w-2xl text-center">
                    <p class="text-xs font-semibold uppercase tracking-widest text-ink-400">{{ t('public.how.kicker') }}</p>
                    <h2 class="mt-3 text-3xl font-bold tracking-tight text-ink-900 dark:text-ink-50">{{ t('public.how.title') }}</h2>
                    <p class="mt-3 text-ink-600 dark:text-ink-300">{{ t('public.how.subtitle') }}</p>
                </div>
                <ol class="relative mt-14 grid gap-10 sm:grid-cols-2 lg:grid-cols-4 lg:gap-6">
                    <!-- Connector line (desktop) -->
                    <div class="absolute left-0 right-0 top-6 hidden h-px bg-gradient-to-r from-transparent via-ink-300 to-transparent dark:via-ink-700 lg:block" aria-hidden="true"></div>
                    <li v-for="(step, i) in steps" :key="i" v-reveal="i * 120" class="relative">
                        <span class="relative z-10 grid h-12 w-12 place-items-center rounded-full border border-ink-300 bg-white text-base font-bold text-ink-900 shadow-subtle dark:border-ink-600 dark:bg-ink-900 dark:text-ink-50">
                            {{ i + 1 }}
                        </span>
                        <h3 class="mt-5 text-base font-semibold text-ink-900 dark:text-ink-50">{{ step.title }}</h3>
                        <p class="mt-2 text-sm leading-relaxed text-ink-600 dark:text-ink-300">{{ step.description }}</p>
                    </li>
                </ol>
            </div>
        </section>

        <!-- About -->
        <section v-if="about.about_heading || about.about_body" class="border-t border-ink-200 bg-ink-50 dark:border-ink-800 dark:bg-ink-900/40">
            <div class="mx-auto grid w-full max-w-7xl items-center gap-12 px-4 py-20 sm:px-6 lg:grid-cols-2 lg:px-8">
                <div v-reveal class="order-2 lg:order-1">
                    <img v-if="aboutImage" :src="aboutImage" alt="" class="w-full rounded-card border border-ink-200 bg-white object-contain p-6 shadow-subtle dark:border-ink-800 dark:bg-ink-900" />
                </div>
                <div v-reveal="120" class="order-1 lg:order-2">
                    <h2 v-if="about.about_heading" class="text-3xl font-bold tracking-tight text-ink-900 dark:text-ink-50">{{ about.about_heading }}</h2>
                    <!-- eslint-disable-next-line vue/no-v-html -->
                    <div v-if="about.about_body" class="prose prose-ink mt-4 max-w-none text-ink-600 dark:prose-invert dark:text-ink-300" v-html="about.about_body"></div>
                    <Link href="/about" class="group mt-6 inline-flex items-center gap-1.5 text-sm font-semibold text-ink-900 dark:text-ink-100">
                        {{ t('public.nav.about') }}
                        <span class="transition-transform group-hover:translate-x-0.5 motion-reduce:group-hover:translate-x-0">→</span>
                    </Link>
                </div>
            </div>
        </section>

        <!-- Testimonials -->
        <section v-if="testimonialCards.length" class="border-t border-ink-200 dark:border-ink-800">
            <div class="mx-auto w-full max-w-7xl px-4 py-20 sm:px-6 lg:px-8">
                <h2 v-reveal class="text-center text-3xl font-bold tracking-tight text-ink-900 dark:text-ink-50">{{ t('public.testimonials.title') }}</h2>
                <div class="mt-12 grid gap-6 md:grid-cols-3">
                    <figure
                        v-for="(tm, i) in testimonialCards"
                        :key="i"
                        v-reveal="i * 100"
                        class="flex flex-col rounded-card border border-ink-200 bg-white p-6 shadow-subtle transition duration-300 hover:shadow-pop dark:border-ink-800 dark:bg-ink-900"
                    >
                        <svg class="h-8 w-8 text-ink-200 dark:text-ink-700" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M9.6 5.2C6 7.1 4 10 4 13.6c0 3 1.8 5.2 4.3 5.2 2.1 0 3.7-1.6 3.7-3.7 0-2-1.4-3.4-3.3-3.4-.3 0-.8.1-.9.1.3-1.9 2-4 3.9-5L9.6 5.2Zm10 0C16 7.1 14 10 14 13.6c0 3 1.8 5.2 4.3 5.2 2.1 0 3.7-1.6 3.7-3.7 0-2-1.4-3.4-3.3-3.4-.3 0-.8.1-.9.1.3-1.9 2-4 3.9-5l-2.1-1.6Z" />
                        </svg>
                        <blockquote class="mt-3 flex-1 leading-relaxed text-ink-700 dark:text-ink-200">{{ tm.quote }}</blockquote>
                        <figcaption v-if="tm.author" class="mt-5 border-t border-ink-100 pt-4 text-sm font-medium text-ink-500 dark:border-ink-800">{{ tm.author }}</figcaption>
                    </figure>
                </div>
            </div>
        </section>

        <!-- Pricing preview -->
        <section v-if="previewPlans.length" class="border-t border-ink-200 bg-ink-50 dark:border-ink-800 dark:bg-ink-900/40">
            <div class="mx-auto w-full max-w-7xl px-4 py-20 sm:px-6 lg:px-8">
                <div v-reveal class="mx-auto max-w-2xl text-center">
                    <h2 class="text-3xl font-bold tracking-tight text-ink-900 dark:text-ink-50">{{ t('public.pricing.title') }}</h2>
                    <p class="mt-3 text-ink-600 dark:text-ink-300">{{ t('public.pricing.subtitle') }}</p>
                </div>
                <div class="mt-12 grid gap-6 md:grid-cols-3">
                    <div v-for="(plan, i) in previewPlans" :key="plan.id" v-reveal="i * 100">
                        <PlanCard :plan="plan" cycle="monthly" :featured="i === 1" class="h-full" />
                    </div>
                </div>
                <div v-reveal class="mt-8 text-center">
                    <Link href="/pricing" class="text-sm font-semibold text-ink-900 hover:underline dark:text-ink-100">
                        {{ t('public.pricing.view_all') }} →
                    </Link>
                </div>
            </div>
        </section>

        <!-- FAQ -->
        <section v-if="faqItems.length" class="border-t border-ink-200 dark:border-ink-800">
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

        <!-- Final CTA band -->
        <section v-if="cta.cta_heading" class="border-t border-ink-200 dark:border-ink-800">
            <div class="mx-auto w-full max-w-7xl px-4 py-20 sm:px-6 lg:px-8">
                <div v-reveal class="relative overflow-hidden rounded-card bg-gradient-to-br from-ink-800 to-ink-950 px-6 py-16 text-center shadow-pop dark:from-ink-900 dark:to-black sm:px-16">
                    <div class="pointer-events-none absolute inset-0 bg-[radial-gradient(circle,rgb(255_255_255/0.06)_1px,transparent_1px)] [background-size:24px_24px]" aria-hidden="true"></div>
                    <h2 class="relative text-3xl font-bold tracking-tight text-white sm:text-4xl">{{ cta.cta_heading }}</h2>
                    <p v-if="cta.cta_subheading" class="relative mx-auto mt-4 max-w-xl text-lg text-ink-300">{{ cta.cta_subheading }}</p>
                    <Link href="/register" class="relative mt-8 inline-flex items-center gap-2 rounded-control bg-white px-7 py-3 font-semibold text-ink-950 shadow-pop transition hover:-translate-y-0.5 hover:bg-ink-100 motion-reduce:hover:translate-y-0">
                        {{ cta.cta_button_text || t('public.nav.register') }}
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12l-7.5 7.5M21 12H3" />
                        </svg>
                    </Link>
                </div>
            </div>
        </section>

        <!-- Demo CTA -->
        <section class="border-t border-ink-200 dark:border-ink-800">
            <div class="mx-auto grid w-full max-w-7xl items-center gap-12 px-4 py-20 sm:px-6 lg:grid-cols-2 lg:px-8">
                <div v-reveal>
                    <h2 class="text-3xl font-bold tracking-tight text-ink-900 dark:text-ink-50">{{ t('public.demo.title') }}</h2>
                    <p class="mt-3 text-ink-600 dark:text-ink-300">{{ t('public.demo.subtitle') }}</p>
                </div>
                <div v-reveal="120" class="rounded-card border border-ink-200 bg-white p-6 shadow-subtle dark:border-ink-800 dark:bg-ink-900">
                    <DemoRequestForm />
                </div>
            </div>
        </section>
    </PublicLayout>
</template>
