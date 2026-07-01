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

// Build the 6 feature cards from the flat CMS map (feature_{i}_title/description).
const featureCards = computed(() =>
    [1, 2, 3, 4, 5, 6]
        .map((i) => ({
            title: props.features[`feature_${i}_title`],
            description: props.features[`feature_${i}_description`],
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
                    <img v-if="hero.hero_image" :src="hero.hero_image" alt="" class="w-full rounded-card border border-ink-200 object-cover shadow-pop dark:border-ink-800" />
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
                    <div v-for="(feature, i) in featureCards" :key="i" class="rounded-card border border-ink-200 bg-white p-6 shadow-subtle dark:border-ink-800 dark:bg-ink-900">
                        <h3 class="text-lg font-semibold text-ink-900 dark:text-ink-50">{{ feature.title }}</h3>
                        <p class="mt-2 text-sm text-ink-600 dark:text-ink-300">{{ feature.description }}</p>
                    </div>
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
