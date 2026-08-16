<script setup>
// Public about page. Content comes from the CMS (about + values + stats
// sections). Rich text bodies are super-admin authored (trusted source).
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import StatCounter from '@/Components/Public/StatCounter.vue';
import { vReveal } from '@/directives/reveal.js';
import { useSeo } from '@/composables/useSeo.js';
import { cmsImage } from '@/cms/defaultImages.js';

const props = defineProps({
    about: { type: Object, default: () => ({}) },
    values: { type: Object, default: () => ({}) },
    stats: { type: Object, default: () => ({}) },
    cta: { type: Object, default: () => ({}) },
});

const { t } = useI18n();

const seo = useSeo({
    title: props.about.about_heading ?? t('public.about.title'),
    description: t('public.about.title'),
});

const aboutImage = computed(() => cmsImage('about_image', props.about.about_image));

// Value cards from the flat CMS map (value_{i}_title/description).
const valueCards = computed(() =>
    [1, 2, 3, 4]
        .map((i) => ({
            title: props.values[`value_${i}_title`],
            description: props.values[`value_${i}_description`],
        }))
        .filter((v) => v.title),
);

// Stats strip (shared with the homepage — same CMS section).
const statItems = computed(() =>
    [1, 2, 3, 4]
        .map((i) => ({
            value: props.stats[`stat_${i}_value`],
            label: props.stats[`stat_${i}_label`],
        }))
        .filter((s) => s.value),
);

// Simple monochrome inline glyphs for the four value cards (order-based).
const valueIcons = [
    'M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z', // check circle
    'M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z', // lock
    'M11.42 15.17 17.25 21A2.652 2.652 0 0 0 21 17.25l-5.877-5.877M11.42 15.17l2.496-3.03c.317-.384.74-.626 1.208-.766M11.42 15.17l-4.655 5.653a2.548 2.548 0 1 1-3.586-3.586l6.837-5.63m5.108-.233c.55-.164 1.163-.188 1.743-.14a4.5 4.5 0 0 0 4.486-6.336l-3.276 3.277a3.004 3.004 0 0 1-2.25-2.25l3.276-3.276a4.5 4.5 0 0 0-6.336 4.486c.091 1.076-.071 2.264-.904 2.95l-.102.085', // wrench
    'M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z', // alert circle
];
</script>

<template>
    <PublicLayout>
        <Head :title="seo.title">
            <meta v-for="m in seo.meta" :key="m.key" :head-key="m.key" :name="m.name" :property="m.property" :content="m.content" />
        </Head>

        <!-- Page hero -->
        <section class="relative overflow-hidden border-b border-ink-200 dark:border-ink-800">
            <div class="pointer-events-none absolute inset-0" aria-hidden="true">
                <div class="absolute -top-24 left-1/2 h-72 w-[46rem] -translate-x-1/2 rounded-full bg-ink-100/80 blur-3xl dark:bg-ink-800/30"></div>
            </div>
            <div class="relative mx-auto w-full max-w-3xl px-4 py-20 text-center sm:px-6 lg:px-8">
                <p v-reveal class="text-xs font-semibold uppercase tracking-widest text-ink-400">{{ t('public.about.kicker') }}</p>
                <h1 v-reveal="80" class="mt-3 text-4xl font-bold tracking-tight text-ink-900 dark:text-ink-50 sm:text-5xl">
                    {{ about.about_heading || t('public.about.title') }}
                </h1>
            </div>
        </section>

        <!-- Story: image + body -->
        <section class="mx-auto grid w-full max-w-7xl items-center gap-12 px-4 py-20 sm:px-6 lg:grid-cols-2 lg:px-8">
            <div v-reveal>
                <img v-if="aboutImage" :src="aboutImage" alt="" class="w-full rounded-card border border-ink-200 bg-white object-contain p-6 shadow-subtle dark:border-ink-800 dark:bg-ink-900" />
            </div>
            <div v-reveal="120">
                <h2 class="text-2xl font-bold tracking-tight text-ink-900 dark:text-ink-50">{{ t('public.about.story_title') }}</h2>
                <!-- eslint-disable-next-line vue/no-v-html -->
                <div class="prose prose-ink mt-4 max-w-none text-ink-600 dark:prose-invert dark:text-ink-300" v-html="about.about_body"></div>
            </div>
        </section>

        <!-- Mission -->
        <section v-if="about.about_mission" class="border-t border-ink-200 bg-ink-50 dark:border-ink-800 dark:bg-ink-900/40">
            <div v-reveal class="mx-auto w-full max-w-3xl px-4 py-16 text-center sm:px-6 lg:px-8">
                <p class="text-xs font-semibold uppercase tracking-widest text-ink-400">{{ t('public.about.mission_title') }}</p>
                <!-- eslint-disable-next-line vue/no-v-html -->
                <div class="prose prose-ink mx-auto mt-5 max-w-none text-xl font-medium leading-relaxed text-ink-800 dark:prose-invert dark:text-ink-100" v-html="about.about_mission"></div>
            </div>
        </section>

        <!-- Values -->
        <section v-if="valueCards.length" class="border-t border-ink-200 dark:border-ink-800">
            <div class="mx-auto w-full max-w-7xl px-4 py-20 sm:px-6 lg:px-8">
                <div v-reveal class="mx-auto max-w-2xl text-center">
                    <h2 class="text-3xl font-bold tracking-tight text-ink-900 dark:text-ink-50">{{ t('public.about.values_title') }}</h2>
                    <p class="mt-3 text-ink-600 dark:text-ink-300">{{ t('public.about.values_subtitle') }}</p>
                </div>
                <div class="mt-12 grid gap-6 sm:grid-cols-2">
                    <div
                        v-for="(value, i) in valueCards"
                        :key="i"
                        v-reveal="i * 80"
                        class="rounded-card border border-ink-200 bg-white p-6 shadow-subtle transition duration-300 hover:-translate-y-1 hover:shadow-pop dark:border-ink-800 dark:bg-ink-900 motion-reduce:hover:translate-y-0"
                    >
                        <span class="inline-flex h-11 w-11 items-center justify-center rounded-control border border-ink-200 bg-ink-50 text-ink-700 dark:border-ink-700 dark:bg-ink-800 dark:text-ink-200">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" :d="valueIcons[i % valueIcons.length]" />
                            </svg>
                        </span>
                        <h3 class="mt-4 text-lg font-semibold text-ink-900 dark:text-ink-50">{{ value.title }}</h3>
                        <p class="mt-2 text-sm leading-relaxed text-ink-600 dark:text-ink-300">{{ value.description }}</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- Stats strip -->
        <section v-if="statItems.length" class="border-t border-ink-200 bg-ink-50 dark:border-ink-800 dark:bg-ink-900/40">
            <div v-reveal class="mx-auto grid w-full max-w-7xl grid-cols-2 gap-x-6 gap-y-10 px-4 py-14 sm:px-6 lg:grid-cols-4 lg:px-8">
                <StatCounter v-for="(stat, i) in statItems" :key="i" :value="stat.value" :label="stat.label" />
            </div>
        </section>

        <!-- CTA -->
        <section v-if="cta.cta_heading" class="border-t border-ink-200 dark:border-ink-800">
            <div v-reveal class="mx-auto w-full max-w-3xl px-4 py-16 text-center sm:px-6 lg:px-8">
                <h2 class="text-2xl font-bold tracking-tight text-ink-900 dark:text-ink-50 sm:text-3xl">{{ cta.cta_heading }}</h2>
                <p v-if="cta.cta_subheading" class="mt-3 text-ink-600 dark:text-ink-300">{{ cta.cta_subheading }}</p>
                <div class="mt-7 flex flex-wrap justify-center gap-3">
                    <Link href="/register" class="rounded-control bg-ink-950 px-6 py-3 font-semibold text-white shadow-subtle transition hover:-translate-y-0.5 hover:bg-ink-800 hover:shadow-pop dark:bg-ink-50 dark:text-ink-950 dark:hover:bg-ink-200 motion-reduce:hover:translate-y-0">
                        {{ cta.cta_button_text || t('public.nav.register') }}
                    </Link>
                    <Link href="/contact" class="rounded-control border border-ink-300 px-6 py-3 font-semibold text-ink-900 transition hover:bg-ink-100 dark:border-ink-700 dark:text-white dark:hover:bg-ink-800">
                        {{ t('public.nav.contact') }}
                    </Link>
                </div>
            </div>
        </section>
    </PublicLayout>
</template>
