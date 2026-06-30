<script setup>
// Tenant-portal forgot-password request. FUNCTIONAL ONLY — design pass later.
// Posts to /app/{tenant_slug}/forgot-password.
import { computed } from 'vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import PublicLayout from '@/Layouts/PublicLayout.vue';

const { t } = useI18n();
const page = usePage();

const slug = computed(() => page.props.tenant.slug);
const status = computed(() => page.props.flash?.success);

const form = useForm({ email: '' });

function submit() {
    form.post(`/app/${slug.value}/forgot-password`);
}
</script>

<template>
    <PublicLayout>
        <Head :title="t('auth.forgot_password_title')" />

        <div class="mx-auto flex min-h-screen max-w-sm flex-col justify-center px-4">
            <h1 class="mb-1 text-2xl font-semibold">{{ t('auth.forgot_password_title') }}</h1>
            <p class="mb-6 text-sm text-slate-500 dark:text-slate-400">{{ t('auth.forgot_password_subtitle') }}</p>

            <p
                v-if="status"
                class="mb-4 rounded border border-green-200 bg-green-50 px-3 py-2 text-sm text-green-800 dark:border-green-900 dark:bg-green-900/30 dark:text-green-300"
            >
                {{ status }}
            </p>

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

                <button
                    type="submit"
                    :disabled="form.processing"
                    class="w-full rounded bg-indigo-600 px-3 py-2 text-white disabled:opacity-50"
                >
                    {{ t('auth.send_reset_link') }}
                </button>
            </form>

            <Link :href="`/app/${slug}/login`" class="mt-6 text-sm text-slate-500 hover:underline dark:text-slate-400">
                {{ t('auth.back_to_login') }}
            </Link>
        </div>
    </PublicLayout>
</template>
