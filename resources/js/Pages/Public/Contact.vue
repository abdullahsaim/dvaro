<script setup>
// Public contact page. Shows CMS-managed contact details + a contact form. The
// form posts to /contact (throttled 3/IP/hour) and is persisted as a demo
// request so it lands in the same super admin queue.
import { computed } from 'vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import PublicLayout from '@/Layouts/PublicLayout.vue';
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
</script>

<template>
    <PublicLayout>
        <Head :title="seo.title">
            <meta v-for="m in seo.meta" :key="m.key" :head-key="m.key" :name="m.name" :property="m.property" :content="m.content" />
        </Head>

        <section class="mx-auto grid w-full max-w-7xl gap-12 px-4 py-20 sm:px-6 lg:grid-cols-2 lg:px-8">
            <!-- Details -->
            <div>
                <h1 class="text-4xl font-bold tracking-tight">{{ t('public.contact.title') }}</h1>
                <p class="mt-3 text-slate-600 dark:text-slate-300">{{ t('public.contact.subtitle') }}</p>

                <dl class="mt-8 space-y-4 text-sm">
                    <div v-if="contact.contact_email">
                        <dt class="font-semibold text-slate-500 dark:text-slate-400">{{ t('public.contact.email') }}</dt>
                        <dd class="mt-1"><a :href="`mailto:${contact.contact_email}`" class="text-indigo-600 hover:underline dark:text-indigo-400">{{ contact.contact_email }}</a></dd>
                    </div>
                    <div v-if="contact.contact_phone">
                        <dt class="font-semibold text-slate-500 dark:text-slate-400">{{ t('public.contact.phone') }}</dt>
                        <dd class="mt-1">{{ contact.contact_phone }}</dd>
                    </div>
                    <div v-if="contact.contact_address">
                        <dt class="font-semibold text-slate-500 dark:text-slate-400">{{ t('public.contact.address') }}</dt>
                        <dd class="mt-1">{{ contact.contact_address }}</dd>
                    </div>
                </dl>
            </div>

            <!-- Form -->
            <div class="rounded-2xl border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900">
                <p v-if="success" class="mb-4 rounded-lg bg-emerald-50 px-4 py-3 text-sm text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300">
                    {{ success }}
                </p>
                <form class="space-y-4" @submit.prevent="submit">
                    <div>
                        <label for="contact_name" class="block text-sm font-medium">{{ t('public.contact.name') }}</label>
                        <input id="contact_name" v-model="form.name" type="text" required
                            class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 dark:border-slate-700 dark:bg-slate-900" />
                        <p v-if="form.errors.name" class="mt-1 text-sm text-red-600">{{ form.errors.name }}</p>
                    </div>
                    <div>
                        <label for="contact_form_email" class="block text-sm font-medium">{{ t('public.contact.email') }}</label>
                        <input id="contact_form_email" v-model="form.email" type="email" required
                            class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 dark:border-slate-700 dark:bg-slate-900" />
                        <p v-if="form.errors.email" class="mt-1 text-sm text-red-600">{{ form.errors.email }}</p>
                    </div>
                    <div>
                        <label for="contact_message" class="block text-sm font-medium">{{ t('public.contact.message') }}</label>
                        <textarea id="contact_message" v-model="form.message" rows="4" required
                            class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 dark:border-slate-700 dark:bg-slate-900"></textarea>
                        <p v-if="form.errors.message" class="mt-1 text-sm text-red-600">{{ form.errors.message }}</p>
                    </div>
                    <button type="submit" :disabled="form.processing"
                        class="rounded-lg bg-indigo-600 px-5 py-2.5 font-semibold text-white transition hover:bg-indigo-500 disabled:opacity-50">
                        {{ form.processing ? t('public.contact.sending') : t('public.contact.send') }}
                    </button>
                </form>
            </div>
        </section>
    </PublicLayout>
</template>
