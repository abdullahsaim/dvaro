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
                <h1 class="text-4xl font-bold tracking-tight text-ink-900 dark:text-ink-50">{{ t('public.contact.title') }}</h1>
                <p class="mt-3 text-ink-600 dark:text-ink-300">{{ t('public.contact.subtitle') }}</p>

                <dl class="mt-8 space-y-4 text-sm">
                    <div v-if="contact.contact_email">
                        <dt class="font-semibold text-ink-500">{{ t('public.contact.email') }}</dt>
                        <dd class="mt-1"><a :href="`mailto:${contact.contact_email}`" class="font-medium text-ink-900 hover:underline dark:text-ink-100">{{ contact.contact_email }}</a></dd>
                    </div>
                    <div v-if="contact.contact_phone">
                        <dt class="font-semibold text-ink-500">{{ t('public.contact.phone') }}</dt>
                        <dd class="mt-1 text-ink-700 dark:text-ink-200">{{ contact.contact_phone }}</dd>
                    </div>
                    <div v-if="contact.contact_address">
                        <dt class="font-semibold text-ink-500">{{ t('public.contact.address') }}</dt>
                        <dd class="mt-1 text-ink-700 dark:text-ink-200">{{ contact.contact_address }}</dd>
                    </div>
                </dl>
            </div>

            <!-- Form -->
            <div class="rounded-card border border-ink-200 bg-white p-6 shadow-subtle dark:border-ink-800 dark:bg-ink-900">
                <p v-if="success" class="mb-4 rounded-control bg-success-50 px-4 py-3 text-sm text-success-700 dark:bg-success-900/20 dark:text-success-300">
                    {{ success }}
                </p>
                <form class="space-y-4" @submit.prevent="submit">
                    <Input v-model="form.name" required :label="t('public.contact.name')" :error="form.errors.name" />
                    <Input v-model="form.email" type="email" required :label="t('public.contact.email')" :error="form.errors.email" />
                    <Textarea v-model="form.message" :rows="4" required :label="t('public.contact.message')" :error="form.errors.message" />
                    <Button type="submit" :loading="form.processing">
                        {{ form.processing ? t('public.contact.sending') : t('public.contact.send') }}
                    </Button>
                </form>
            </div>
        </section>
    </PublicLayout>
</template>
