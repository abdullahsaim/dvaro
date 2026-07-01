<script setup>
// Super admin panel login. Design-system pass.
// Posts to /superadmin/login. No tenant context (platform-wide guard).
import { Head, useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import Button from '@/Components/UI/Button.vue';
import Input from '@/Components/UI/Input.vue';

const { t } = useI18n();

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

function submit() {
    form.post('/superadmin/login', {
        onFinish: () => form.reset('password'),
    });
}
</script>

<template>
    <PublicLayout>
        <Head :title="t('superadmin.sign_in')" />

        <div class="mx-auto flex min-h-[70vh] max-w-sm flex-col justify-center px-4 py-16">
            <div class="rounded-card border border-ink-200 bg-white p-6 shadow-subtle dark:border-ink-800 dark:bg-ink-900">
                <p class="mb-1 text-xs font-semibold uppercase tracking-wide text-ink-500">{{ t('superadmin.panel') }}</p>
                <h1 class="mb-6 text-2xl font-semibold text-ink-900 dark:text-ink-50">{{ t('superadmin.sign_in') }}</h1>

                <form class="space-y-4" @submit.prevent="submit">
                    <Input v-model="form.email" type="email" autocomplete="username" required :label="t('auth.email')" :error="form.errors.email" />
                    <Input v-model="form.password" type="password" autocomplete="current-password" required :label="t('auth.password')" :error="form.errors.password" />

                    <label class="flex items-center gap-2 text-sm text-ink-700 dark:text-ink-300">
                        <input v-model="form.remember" type="checkbox" class="h-4 w-4 rounded border-ink-300 accent-ink-900 dark:border-ink-700 dark:accent-ink-100" />
                        {{ t('auth.remember_me') }}
                    </label>

                    <Button type="submit" class="w-full" :loading="form.processing">{{ t('auth.sign_in') }}</Button>
                </form>
            </div>
        </div>
    </PublicLayout>
</template>
