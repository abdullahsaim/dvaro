<script setup>
// Public about page. Content comes from the CMS (about section). The body is a
// richtext block rendered as HTML (super-admin authored, trusted source).
import { Head } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import { useSeo } from '@/composables/useSeo.js';

const props = defineProps({
    about: { type: Object, default: () => ({}) },
});

const { t } = useI18n();

const seo = useSeo({
    title: props.about.about_heading ?? t('public.about.title'),
    description: t('public.about.title'),
});
</script>

<template>
    <PublicLayout>
        <Head :title="seo.title">
            <meta v-for="m in seo.meta" :key="m.key" :head-key="m.key" :name="m.name" :property="m.property" :content="m.content" />
        </Head>

        <section class="mx-auto w-full max-w-3xl px-4 py-20 sm:px-6 lg:px-8">
            <h1 class="text-4xl font-bold tracking-tight">{{ about.about_heading }}</h1>
            <!-- eslint-disable-next-line vue/no-v-html -->
            <div class="prose prose-slate mt-6 max-w-none dark:prose-invert" v-html="about.about_body"></div>
        </section>
    </PublicLayout>
</template>
