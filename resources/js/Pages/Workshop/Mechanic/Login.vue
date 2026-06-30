<script setup>
// Mechanic-portal login. FUNCTIONAL ONLY — design pass later.
// Two modes: full password, or a quick numeric PIN. Posts to
// /mechanic/{tenant_slug}/login; tenant slug comes from shared props.
import { computed, ref } from 'vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import PublicLayout from '@/Layouts/PublicLayout.vue';

const { t } = useI18n();
const page = usePage();

const loginUrl = computed(() => `/mechanic/${page.props.tenant.slug}/login`);
const forgotUrl = computed(() => `/mechanic/${page.props.tenant.slug}/forgot-password`);

const usePin = ref(false);

const form = useForm({
    email: '',
    password: '',
    pin: '',
});

function submit() {
    // Only send the credential for the active mode so required_without passes.
    form.transform((data) => ({
        email: data.email,
        password: usePin.value ? '' : data.password,
        pin: usePin.value ? data.pin : '',
    })).post(loginUrl.value, {
        onFinish: () => form.reset('password', 'pin'),
    });
}
</script>

<template>
    <PublicLayout>
        <Head :title="t('workshop.sign_in')" />

        <div class="mx-auto flex min-h-screen max-w-sm flex-col justify-center px-4">
            <h1 class="mb-1 text-2xl font-semibold">{{ t('workshop.portal_title') }}</h1>
            <p class="mb-6 text-sm text-slate-500 dark:text-slate-400">{{ t('workshop.sign_in') }}</p>

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

                <div v-if="!usePin">
                    <label for="password" class="block text-sm font-medium">{{ t('auth.password') }}</label>
                    <input
                        id="password"
                        v-model="form.password"
                        type="password"
                        autocomplete="current-password"
                        class="mt-1 w-full rounded border border-slate-300 px-3 py-2 dark:border-slate-700 dark:bg-slate-900"
                    />
                    <p v-if="form.errors.password" class="mt-1 text-sm text-red-600">{{ form.errors.password }}</p>
                </div>

                <div v-else>
                    <label for="pin" class="block text-sm font-medium">{{ t('auth.pin') }}</label>
                    <input
                        id="pin"
                        v-model="form.pin"
                        type="password"
                        inputmode="numeric"
                        autocomplete="off"
                        class="mt-1 w-full rounded border border-slate-300 px-3 py-2 tracking-widest dark:border-slate-700 dark:bg-slate-900"
                    />
                    <p v-if="form.errors.pin" class="mt-1 text-sm text-red-600">{{ form.errors.pin }}</p>
                </div>

                <button
                    type="submit"
                    :disabled="form.processing"
                    class="w-full rounded bg-indigo-600 px-3 py-2 text-white disabled:opacity-50"
                >
                    {{ t('workshop.sign_in') }}
                </button>

                <button
                    type="button"
                    class="w-full text-center text-sm text-slate-500 hover:underline dark:text-slate-400"
                    @click="usePin = !usePin"
                >
                    {{ usePin ? t('auth.use_password') : t('auth.use_pin') }}
                </button>
            </form>

            <Link :href="forgotUrl" class="mt-4 text-sm text-slate-500 hover:underline dark:text-slate-400">
                {{ t('auth.forgot_password') }}
            </Link>
        </div>
    </PublicLayout>
</template>
