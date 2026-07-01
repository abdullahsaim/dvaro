<script setup>
// Public homepage. All copy comes from the CMS (passed as section maps); the
// pricing preview is live plan data. FUNCTIONAL ONLY — design pass later.
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import DemoRequestForm from '@/Components/Public/DemoRequestForm.vue';
import PlanCard from '@/Components/Public/PlanCard.vue';
import { useSeo } from '@/composables/useSeo.js';
import { cmsImage } from '@/cms/defaultImages.js';

const props = defineProps({
    hero: { type: Object, default: () => ({}) },
    features: { type: Object, default: () => ({}) },
    about: { type: Object, default: () => ({}) },
    testimonials: { type: Object, default: () => ({}) },
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

// Build testimonials from the flat CMS map (testimonial_{i}_quote/author).
const testimonialCards = computed(() =>
    [1, 2, 3]
        .map((i) => ({
            quote: props.testimonials[`testimonial_${i}_quote`],
            author: props.testimonials[`testimonial_${i}_author`],
        }))
        .filter((tm) => tm.quote),
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
            <div class="mx-auto grid w-full max-w-7xl items-center gap-12 px-4 py-20 sm:px-6 lg:grid-cols-2 lg:px-8 lg:py-28">
                <div>
                    <h1 class="text-4xl font-bold tracking-tight text-ink-900 dark:text-ink-50 sm:text-5xl">{{ hero.hero_heading }}</h1>
                    <p class="mt-5 max-w-xl text-lg text-ink-600 dark:text-ink-300">{{ hero.hero_subheading }}</p>
                    <div class="mt-8 flex flex-wrap gap-3">
                        <Link href="/register" class="rounded-control bg-ink-950 px-6 py-3 font-semibold text-white shadow-subtle transition hover:bg-ink-800 dark:bg-ink-50 dark:text-ink-950 dark:hover:bg-ink-200">
                            {{ hero.hero_cta_text || t('public.nav.register') }}
                        </Link>
                        <Link href="/pricing" class="rounded-control border border-ink-300 px-6 py-3 font-semibold text-ink-900 transition hover:bg-ink-100 dark:border-ink-700 dark:text-white dark:hover:bg-ink-800">
                            {{ t('public.pricing.view_all') }}
                        </Link>
                    </div>
                </div>
                <div class="relative">
                    <img v-if="heroImage" :src="heroImage" alt="" class="aspect-[4/3] w-full rounded-card border border-ink-200 object-cover shadow-pop dark:border-ink-800" />
                    <div v-else class="aspect-[4/3] w-full rounded-card bg-gradient-to-br from-ink-700 to-ink-950 dark:from-ink-800 dark:to-ink-950"></div>
                </div>
            </div>
        </section>

        <!-- Features -->
        <section class="border-t border-ink-200 bg-ink-50 dark:border-ink-800 dark:bg-ink-900/40">
            <div class="mx-auto w-full max-w-7xl px-4 py-20 sm:px-6 lg:px-8">
                <div class="mx-auto max-w-2xl text-center">
                    <h2 class="text-3xl font-bold tracking-tight text-ink-900 dark:text-ink-50">{{ t('public.features.title') }}</h2>
                    <p class="mt-3 text-ink-600 dark:text-ink-300">{{ t('public.features.subtitle') }}</p>
                </div>
                <div class="mt-12 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                    <div v-for="(feature, i) in featureCards" :key="i" class="rounded-card border border-ink-200 bg-white p-6 shadow-subtle transition hover:shadow-pop dark:border-ink-800 dark:bg-ink-900">
                        <div v-if="feature.image" class="mb-4 inline-flex h-12 w-12 items-center justify-center rounded-control border border-ink-200 bg-ink-50 dark:border-ink-700 dark:bg-ink-800">
                            <img :src="feature.image" alt="" class="h-7 w-7" />
                        </div>
                        <h3 class="text-lg font-semibold text-ink-900 dark:text-ink-50">{{ feature.title }}</h3>
                        <p class="mt-2 text-sm text-ink-600 dark:text-ink-300">{{ feature.description }}</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- About -->
        <section v-if="about.about_heading || about.about_body" class="border-t border-ink-200 dark:border-ink-800">
            <div class="mx-auto grid w-full max-w-7xl items-center gap-12 px-4 py-20 sm:px-6 lg:grid-cols-2 lg:px-8">
                <div class="order-2 lg:order-1">
                    <img v-if="aboutImage" :src="aboutImage" alt="" class="w-full rounded-card border border-ink-200 bg-white object-contain p-6 shadow-subtle dark:border-ink-800 dark:bg-ink-900" />
                </div>
                <div class="order-1 lg:order-2">
                    <h2 v-if="about.about_heading" class="text-3xl font-bold tracking-tight text-ink-900 dark:text-ink-50">{{ about.about_heading }}</h2>
                    <div v-if="about.about_body" class="prose prose-ink mt-4 max-w-none text-ink-600 dark:prose-invert dark:text-ink-300" v-html="about.about_body"></div>
                    <Link href="/about" class="mt-6 inline-block text-sm font-semibold text-ink-900 hover:underline dark:text-ink-100">
                        {{ t('public.nav.about') }} →
                    </Link>
                </div>
            </div>
        </section>

        <!-- Pricing preview -->
        <section v-if="previewPlans.length" class="border-t border-ink-200 dark:border-ink-800">
            <div class="mx-auto w-full max-w-7xl px-4 py-20 sm:px-6 lg:px-8">
                <div class="mx-auto max-w-2xl text-center">
                    <h2 class="text-3xl font-bold tracking-tight text-ink-900 dark:text-ink-50">{{ t('public.pricing.title') }}</h2>
                    <p class="mt-3 text-ink-600 dark:text-ink-300">{{ t('public.pricing.subtitle') }}</p>
                </div>
                <div class="mt-12 grid gap-6 md:grid-cols-3">
                    <PlanCard
                        v-for="(plan, i) in previewPlans"
                        :key="plan.id"
                        :plan="plan"
                        cycle="monthly"
                        :featured="i === 1"
                    />
                </div>
                <div class="mt-8 text-center">
                    <Link href="/pricing" class="text-sm font-semibold text-ink-900 hover:underline dark:text-ink-100">
                        {{ t('public.pricing.view_all') }} →
                    </Link>
                </div>
            </div>
        </section>

        <!-- Testimonials -->
        <section v-if="testimonialCards.length" class="border-t border-ink-200 bg-ink-50 dark:border-ink-800 dark:bg-ink-900/40">
            <div class="mx-auto w-full max-w-7xl px-4 py-20 sm:px-6 lg:px-8">
                <h2 class="text-center text-3xl font-bold tracking-tight text-ink-900 dark:text-ink-50">{{ t('public.testimonials.title') }}</h2>
                <div class="mt-12 grid gap-6 md:grid-cols-2">
                    <figure v-for="(tm, i) in testimonialCards" :key="i" class="rounded-card border border-ink-200 bg-white p-6 shadow-subtle dark:border-ink-800 dark:bg-ink-900">
                        <blockquote class="text-ink-700 dark:text-ink-200">“{{ tm.quote }}”</blockquote>
                        <figcaption v-if="tm.author" class="mt-4 text-sm font-medium text-ink-500">— {{ tm.author }}</figcaption>
                    </figure>
                </div>
            </div>
        </section>

        <!-- Demo CTA -->
        <section class="border-t border-ink-200 dark:border-ink-800">
            <div class="mx-auto grid w-full max-w-7xl items-center gap-12 px-4 py-20 sm:px-6 lg:grid-cols-2 lg:px-8">
                <div>
                    <h2 class="text-3xl font-bold tracking-tight text-ink-900 dark:text-ink-50">{{ t('public.demo.title') }}</h2>
                    <p class="mt-3 text-ink-600 dark:text-ink-300">{{ t('public.demo.subtitle') }}</p>
                </div>
                <div class="rounded-card border border-ink-200 bg-white p-6 shadow-subtle dark:border-ink-800 dark:bg-ink-900">
                    <DemoRequestForm />
                </div>
            </div>
        </section>
    </PublicLayout>
</template>
