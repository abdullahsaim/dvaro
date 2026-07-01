<script setup>
// Public tenant self-registration. Design-system pass.
// Pre-tenant route: posts to /register (no tenant slug in scope). On success
// the server logs the new admin in and redirects to their dashboard.
import { Head, useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import Button from '@/Components/UI/Button.vue';
import Input from '@/Components/UI/Input.vue';

const { t } = useI18n();

const form = useForm({
    company_name: '',
    email: '',
    password: '',
    password_confirmation: '',
});

function submit() {
    form.post('/register', {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
}
</script>

<template>
    <PublicLayout>
        <Head :title="t('auth.register')" />

        <div class="mx-auto flex min-h-[70vh] max-w-sm flex-col justify-center px-4 py-16">
            <div class="rounded-card border border-ink-200 bg-white p-6 shadow-subtle dark:border-ink-800 dark:bg-ink-900">
                <h1 class="mb-6 text-2xl font-semibold text-ink-900 dark:text-ink-50">{{ t('auth.register') }}</h1>

                <form class="space-y-4" @submit.prevent="submit">
                    <Input v-model="form.company_name" autocomplete="organization" required :label="t('auth.company_name')" :error="form.errors.company_name" />
                    <Input v-model="form.email" type="email" autocomplete="username" required :label="t('auth.email')" :error="form.errors.email" />
                    <Input v-model="form.password" type="password" autocomplete="new-password" required :label="t('auth.password')" :error="form.errors.password" />
                    <Input v-model="form.password_confirmation" type="password" autocomplete="new-password" required :label="t('auth.confirm_password')" />

                    <Button type="submit" class="w-full" :loading="form.processing">{{ t('auth.create_account') }}</Button>
                </form>
            </div>
        </div>
    </PublicLayout>
</template>
