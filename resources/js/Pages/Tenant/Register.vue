<script setup>
// Public tenant self-registration. FUNCTIONAL ONLY — design pass comes later.
// Pre-tenant route: posts to /register (no tenant slug in scope). On success
// the server logs the new admin in and redirects to their dashboard.
import { Head, useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import PublicLayout from '@/Layouts/PublicLayout.vue';

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

        <div class="mx-auto flex min-h-screen max-w-sm flex-col justify-center px-4">
            <h1 class="mb-6 text-2xl font-semibold">{{ t('auth.register') }}</h1>

            <form class="space-y-4" @submit.prevent="submit">
                <div>
                    <label for="company_name" class="block text-sm font-medium">{{ t('auth.company_name') }}</label>
                    <input
                        id="company_name"
                        v-model="form.company_name"
                        type="text"
                        autocomplete="organization"
                        required
                        class="mt-1 w-full rounded border border-slate-300 px-3 py-2 dark:border-slate-700 dark:bg-slate-900"
                    />
                    <p v-if="form.errors.company_name" class="mt-1 text-sm text-red-600">{{ form.errors.company_name }}</p>
                </div>

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
                        autocomplete="new-password"
                        required
                        class="mt-1 w-full rounded border border-slate-300 px-3 py-2 dark:border-slate-700 dark:bg-slate-900"
                    />
                    <p v-if="form.errors.password" class="mt-1 text-sm text-red-600">{{ form.errors.password }}</p>
                </div>

                <div>
                    <label for="password_confirmation" class="block text-sm font-medium">{{ t('auth.confirm_password') }}</label>
                    <input
                        id="password_confirmation"
                        v-model="form.password_confirmation"
                        type="password"
                        autocomplete="new-password"
                        required
                        class="mt-1 w-full rounded border border-slate-300 px-3 py-2 dark:border-slate-700 dark:bg-slate-900"
                    />
                </div>

                <button
                    type="submit"
                    :disabled="form.processing"
                    class="w-full rounded bg-indigo-600 px-3 py-2 text-white disabled:opacity-50"
                >
                    {{ t('auth.create_account') }}
                </button>
            </form>
        </div>
    </PublicLayout>
</template>
