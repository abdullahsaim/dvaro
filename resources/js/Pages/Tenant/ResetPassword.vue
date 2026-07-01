<script setup>
// Tenant-portal reset-password form. Design-system pass.
// Posts to /app/{tenant_slug}/reset-password with the token + email from the link.
import { computed } from 'vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import Button from '@/Components/UI/Button.vue';
import Input from '@/Components/UI/Input.vue';

const props = defineProps({
    token: { type: String, required: true },
    email: { type: String, default: '' },
});

const { t } = useI18n();
const page = usePage();
const slug = computed(() => page.props.tenant.slug);

const form = useForm({
    token: props.token,
    email: props.email,
    password: '',
    password_confirmation: '',
});

function submit() {
    form.post(`/app/${slug.value}/reset-password`, {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
}
</script>

<template>
    <PublicLayout>
        <Head :title="t('auth.reset_password_title')" />

        <div class="mx-auto flex min-h-[70vh] max-w-sm flex-col justify-center px-4 py-16">
            <div class="rounded-card border border-ink-200 bg-white p-6 shadow-subtle dark:border-ink-800 dark:bg-ink-900">
                <h1 class="mb-1 text-2xl font-semibold text-ink-900 dark:text-ink-50">{{ t('auth.reset_password_title') }}</h1>
                <p class="mb-6 text-sm text-ink-500">{{ t('auth.reset_password_subtitle') }}</p>

                <form class="space-y-4" @submit.prevent="submit">
                    <Input v-model="form.email" type="email" autocomplete="username" required :label="t('auth.email')" :error="form.errors.email" />
                    <Input v-model="form.password" type="password" autocomplete="new-password" required :label="t('auth.new_password')" :error="form.errors.password" />
                    <Input v-model="form.password_confirmation" type="password" autocomplete="new-password" required :label="t('auth.confirm_password')" />

                    <Button type="submit" class="w-full" :loading="form.processing">{{ t('auth.update_password') }}</Button>
                </form>

                <Link :href="`/app/${slug}/login`" class="mt-6 inline-block text-sm text-ink-500 hover:underline">
                    {{ t('auth.back_to_login') }}
                </Link>
            </div>
        </div>
    </PublicLayout>
</template>
