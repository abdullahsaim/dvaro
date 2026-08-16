<script setup>
// Public contact page. Shows CMS-managed contact details + a contact form. The
// form posts to /contact (throttled 3/IP/hour) and is persisted as a demo
// request so it lands in the same super admin queue.
import { computed } from 'vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import Button from '@/Components/UI/Button.vue';
import Input from '@/Components/UI/Input.vue';
import Textarea from '@/Components/UI/Textarea.vue';
import { vReveal } from '@/directives/reveal.js';
import { useSeo } from '@/composables/useSeo.js';

const props = defineProps({
    contact: { type: Object, default: () => ({}) },
});

const { t } = useI18n();
const page = usePage();

const seo = useSeo({
    title: t('public.contact.title'),
    description: t('public.contact.subtitle'),
});

const success = computed(() => page.props.flash?.success ?? null);

const form = useForm({
    name: '',
    email: '',
    message: '',
});

function submit() {
    form.post('/contact', {
        preserveScroll: true,
        onSuccess: () => form.reset(),
    });
}

// Contact info cards — icon path + label key + CMS value (+ optional href).
const infoCards = computed(() =>
    [
        {
            label: 'public.contact.email',
            value: props.contact.contact_email,
            href: props.contact.contact_email ? `mailto:${props.contact.contact_email}` : null,
            icon: 'M21.75 6.75v10.5a2.25 2.25 0 0 1-2.25 2.25h-15a2.25 2.25 0 0 1-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25m19.5 0v.243a2.25 2.25 0 0 1-1.07 1.916l-7.5 4.615a2.25 2.25 0 0 1-2.36 0L3.32 8.91a2.25 2.25 0 0 1-1.07-1.916V6.75',
        },
        {
            label: 'public.contact.phone',
            value: props.contact.contact_phone,
            href: props.contact.contact_phone ? `tel:${props.contact.contact_phone.replace(/\s+/g, '')}` : null,
            icon: 'M2.25 6.75c0 8.284 6.716 15 15 15h2.25a2.25 2.25 0 0 0 2.25-2.25v-1.372c0-.516-.351-.966-.852-1.091l-4.423-1.106c-.44-.11-.902.055-1.173.417l-.97 1.293c-.282.376-.769.542-1.21.38a12.035 12.035 0 0 1-7.143-7.143c-.162-.441.004-.928.38-1.21l1.293-.97c.363-.271.527-.734.417-1.173L6.963 3.102a1.125 1.125 0 0 0-1.091-.852H4.5A2.25 2.25 0 0 0 2.25 4.5v2.25Z',
        },
        {
            label: 'public.contact.address',
            value: props.contact.contact_address,
            href: null,
            icon: 'M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1 1 15 0Z',
        },
        {
            label: 'public.contact.hours',
            value: props.contact.contact_hours,
            href: null,
            icon: 'M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z',
        },
    ].filter((c) => c.value),
);
</script>

<template>
    <PublicLayout>
        <Head :title="seo.title">
            <meta v-for="m in seo.meta" :key="m.key" :head-key="m.key" :name="m.name" :property="m.property" :content="m.content" />
        </Head>

        <section class="relative overflow-hidden">
            <!-- Decorative dot grid -->
            <div class="pointer-events-none absolute inset-0 bg-[radial-gradient(circle,rgb(0_0_0/0.04)_1px,transparent_1px)] [background-size:28px_28px] [mask-image:linear-gradient(to_bottom,black,transparent_60%)] dark:bg-[radial-gradient(circle,rgb(255_255_255/0.05)_1px,transparent_1px)]" aria-hidden="true"></div>

            <div class="relative mx-auto w-full max-w-7xl px-4 py-20 sm:px-6 lg:px-8">
                <div v-reveal class="mx-auto max-w-2xl text-center">
                    <p class="text-xs font-semibold uppercase tracking-widest text-ink-400">{{ t('public.contact.kicker') }}</p>
                    <h1 class="mt-3 text-4xl font-bold tracking-tight text-ink-900 dark:text-ink-50 sm:text-5xl">{{ t('public.contact.title') }}</h1>
                    <p class="mt-4 text-lg text-ink-600 dark:text-ink-300">{{ t('public.contact.subtitle') }}</p>
                </div>

                <div class="mt-14 grid gap-10 lg:grid-cols-5">
                    <!-- Details -->
                    <div v-reveal class="lg:col-span-2">
                        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-1">
                            <component
                                :is="card.href ? 'a' : 'div'"
                                v-for="card in infoCards"
                                :key="card.label"
                                :href="card.href || undefined"
                                class="group flex items-start gap-4 rounded-card border border-ink-200 bg-white p-5 shadow-subtle transition duration-300 dark:border-ink-800 dark:bg-ink-900"
                                :class="card.href ? 'hover:-translate-y-0.5 hover:shadow-pop motion-reduce:hover:translate-y-0' : ''"
                            >
                                <span class="grid h-10 w-10 shrink-0 place-items-center rounded-control border border-ink-200 bg-ink-50 text-ink-700 dark:border-ink-700 dark:bg-ink-800 dark:text-ink-200">
                                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" :d="card.icon" />
                                    </svg>
                                </span>
                                <span>
                                    <span class="block text-xs font-semibold uppercase tracking-wide text-ink-400">{{ t(card.label) }}</span>
                                    <span class="mt-1 block font-medium text-ink-900 dark:text-ink-100" :class="card.href ? 'group-hover:underline' : ''">{{ card.value }}</span>
                                </span>
                            </component>
                        </div>

                        <p v-if="contact.contact_response_time" class="mt-6 flex items-center gap-2 text-sm text-ink-500 dark:text-ink-400">
                            <svg class="h-4 w-4 shrink-0 text-success-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                            </svg>
                            {{ contact.contact_response_time }}
                        </p>
                    </div>

                    <!-- Form -->
                    <div v-reveal="120" class="lg:col-span-3">
                        <div class="rounded-card border border-ink-200 bg-white p-6 shadow-subtle dark:border-ink-800 dark:bg-ink-900 sm:p-8">
                            <div v-if="success" class="animate-fade-in-up mb-5 flex items-start gap-3 rounded-control bg-success-50 px-4 py-3 text-sm text-success-700 dark:bg-success-900/20 dark:text-success-300">
                                <svg class="mt-0.5 h-4 w-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                                </svg>
                                {{ success }}
                            </div>
                            <form class="space-y-5" @submit.prevent="submit">
                                <div class="grid gap-5 sm:grid-cols-2">
                                    <Input v-model="form.name" required :label="t('public.contact.name')" :error="form.errors.name" />
                                    <Input v-model="form.email" type="email" required :label="t('public.contact.email')" :error="form.errors.email" />
                                </div>
                                <Textarea v-model="form.message" :rows="6" required :label="t('public.contact.message')" :error="form.errors.message" />
                                <Button type="submit" :loading="form.processing" class="w-full sm:w-auto">
                                    {{ form.processing ? t('public.contact.sending') : t('public.contact.send') }}
                                </Button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </PublicLayout>
</template>
