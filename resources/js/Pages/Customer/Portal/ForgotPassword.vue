<script setup>
// Customer-portal forgot-password request. Design-system pass.
// Posts to /portal/{tenant_slug}/forgot-password.
import { computed } from 'vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import Button from '@/Components/UI/Button.vue';
import Input from '@/Components/UI/Input.vue';

const { t } = useI18n();
const page = usePage();

const slug = computed(() => page.props.tenant.slug);
const status = computed(() => page.props.flash?.success);

const form = useForm({ email: '' });

function submit() {
    form.post(`/portal/${slug.value}/forgot-password`);
}
</script>

<template>
    <PublicLayout>
        <Head :title="t('auth.forgot_password_title')" />

        <div class="mx-auto flex min-h-[70vh] max-w-sm flex-col justify-center px-4 py-16">
            <div class="rounded-card border border-ink-200 bg-white p-6 shadow-subtle dark:border-ink-800 dark:bg-ink-900">
                <h1 class="mb-1 text-2xl font-semibold text-ink-900 dark:text-ink-50">{{ t('auth.forgot_password_title') }}</h1>
                <p class="mb-6 text-sm text-ink-500">{{ t('auth.forgot_password_subtitle') }}</p>

                <p
                    v-if="status"
                    class="mb-4 rounded-control border border-success-200 bg-success-50 px-3 py-2 text-sm text-success-700 dark:border-success-900 dark:bg-success-900/20 dark:text-success-300"
                >
                    {{ status }}
                </p>

                <form class="space-y-4" @submit.prevent="submit">
                    <Input v-model="form.email" type="email" autocomplete="username" required :label="t('auth.email')" :error="form.errors.email" />
                    <Button type="submit" class="w-full" :loading="form.processing">{{ t('auth.send_reset_link') }}</Button>
                </form>

                <Link :href="`/portal/${slug}/login`" class="mt-6 inline-block text-sm text-ink-500 hover:underline">
                    {{ t('auth.back_to_login') }}
                </Link>
            </div>
        </div>
    </PublicLayout>
</template>
