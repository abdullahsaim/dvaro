<script setup>
// Public pricing page. Shows every ACTIVE plan (inactive plans are filtered out
// server-side) with a monthly/annual toggle. Pricing is always live plan data.
import { ref } from 'vue';
import { Head } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import PlanCard from '@/Components/Public/PlanCard.vue';
import { useSeo } from '@/composables/useSeo.js';

defineProps({
    plans: { type: Array, default: () => [] },
});

const { t } = useI18n();

const seo = useSeo({
    title: t('public.pricing.title'),
    description: t('public.pricing.subtitle'),
});

const cycle = ref('monthly');
</script>

<template>
    <PublicLayout>
        <Head :title="seo.title">
            <meta v-for="m in seo.meta" :key="m.key" :head-key="m.key" :name="m.name" :property="m.property" :content="m.content" />
        </Head>

        <section class="mx-auto w-full max-w-7xl px-4 py-20 sm:px-6 lg:px-8">
            <div class="mx-auto max-w-2xl text-center">
                <h1 class="text-4xl font-bold tracking-tight">{{ t('public.pricing.title') }}</h1>
                <p class="mt-3 text-slate-600 dark:text-slate-300">{{ t('public.pricing.subtitle') }}</p>
            </div>

            <!-- Monthly / Annual toggle -->
            <div class="mt-8 flex justify-center">
                <div class="inline-flex rounded-lg border border-slate-200 p-1 dark:border-slate-800">
                    <button
                        type="button"
                        class="rounded-md px-4 py-1.5 text-sm font-medium transition"
                        :class="cycle === 'monthly' ? 'bg-indigo-600 text-white' : 'text-slate-600 dark:text-slate-300'"
                        @click="cycle = 'monthly'"
                    >
                        {{ t('public.pricing.monthly') }}
                    </button>
                    <button
                        type="button"
                        class="rounded-md px-4 py-1.5 text-sm font-medium transition"
                        :class="cycle === 'annual' ? 'bg-indigo-600 text-white' : 'text-slate-600 dark:text-slate-300'"
                        @click="cycle = 'annual'"
                    >
                        {{ t('public.pricing.annual') }}
                    </button>
                </div>
            </div>

            <p v-if="!plans.length" class="mt-12 text-center text-slate-500 dark:text-slate-400">
                {{ t('public.pricing.no_plans') }}
            </p>

            <div v-else class="mx-auto mt-12 grid max-w-5xl gap-6 md:grid-cols-2 lg:grid-cols-3">
                <PlanCard
                    v-for="(plan, i) in plans"
                    :key="plan.id"
                    :plan="plan"
                    :cycle="cycle"
                    :featured="plans.length === 3 && i === 1"
                />
            </div>
        </section>
    </PublicLayout>
</template>
