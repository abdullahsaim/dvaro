<script setup>
// Super admin panel login. FUNCTIONAL ONLY — design pass later.
// Posts to /superadmin/login. No tenant context (platform-wide guard).
import { Head, useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import PublicLayout from '@/Layouts/PublicLayout.vue';

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

        <div class="mx-auto flex min-h-screen max-w-sm flex-col justify-center px-4">
            <p class="mb-1 text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                {{ t('superadmin.panel') }}
            </p>
            <h1 class="mb-6 text-2xl font-semibold">{{ t('superadmin.sign_in') }}</h1>

            <form class="space-y-4" @submit.prevent="submit">
                <div>
                    <label for="email" class="block text-sm font-medium">{{ t('auth.email') }}</label>
                    <input
                        id="email"
                        v-model="form.email"
                        type="email"
                        autocomplete="username"
                        required
                        class="mt-1 w-full rounded border border-slate-300 px-3 py-2 dark:border-slate-700 dark:bg-slate-900"
                    />
                    <p v-if="form.errors.email" class="mt-1 text-sm text-red-600">{{ form.errors.email }}</p>
                </div>

                <div>
                    <label for="password" class="block text-sm font-medium">{{ t('auth.password') }}</label>
                    <input
                        id="password"
                        v-model="form.password"
                        type="password"
                        autocomplete="current-password"
                        required
                        class="mt-1 w-full rounded border border-slate-300 px-3 py-2 dark:border-slate-700 dark:bg-slate-900"
                    />
                    <p v-if="form.errors.password" class="mt-1 text-sm text-red-600">{{ form.errors.password }}</p>
                </div>

                <label class="flex items-center gap-2 text-sm">
                    <input v-model="form.remember" type="checkbox" />
                    {{ t('auth.remember_me') }}
                </label>

                <button
                    type="submit"
                    :disabled="form.processing"
                    class="w-full rounded bg-indigo-600 px-3 py-2 text-white disabled:opacity-50"
                >
                    {{ t('auth.sign_in') }}
                </button>
            </form>
        </div>
    </PublicLayout>
</template>
