<script setup>
// Customer-portal login. Design-system pass.
// Posts to /portal/{tenant_slug}/login; tenant slug comes from shared props
// (ResolveTenantForCustomer shares the slim tenant payload on this route).
import { computed } from 'vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import Button from '@/Components/UI/Button.vue';
import Input from '@/Components/UI/Input.vue';

const { t } = useI18n();
const page = usePage();

const loginUrl = computed(() => `/portal/${page.props.tenant.slug}/login`);
const forgotUrl = computed(() => `/portal/${page.props.tenant.slug}/forgot-password`);

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

function submit() {
    form.post(loginUrl.value, {
        onFinish: () => form.reset('password'),
    });
}
</script>

<template>
    <PublicLayout>
        <Head :title="t('customer.portal.login_title')" />

        <div class="mx-auto flex min-h-[70vh] max-w-sm flex-col justify-center px-4 py-16">
            <div class="rounded-card border border-ink-200 bg-white p-6 shadow-subtle dark:border-ink-800 dark:bg-ink-900">
                <h1 class="text-2xl font-semibold text-ink-900 dark:text-ink-50">{{ t('customer.portal.login_title') }}</h1>
                <p class="mb-6 mt-1 text-sm text-ink-500">{{ t('customer.portal.login_subtitle') }}</p>

                <form class="space-y-4" @submit.prevent="submit">
                    <Input v-model="form.email" type="email" autocomplete="username" required :label="t('auth.email')" :error="form.errors.email" />
                    <Input v-model="form.password" type="password" autocomplete="current-password" required :label="t('auth.password')" :error="form.errors.password" />

                    <label class="flex items-center gap-2 text-sm text-ink-700 dark:text-ink-300">
                        <input v-model="form.remember" type="checkbox" class="h-4 w-4 rounded border-ink-300 accent-ink-900 dark:border-ink-700 dark:accent-ink-100" />
                        {{ t('auth.remember_me') }}
                    </label>

                    <Button type="submit" class="w-full" :loading="form.processing">{{ t('auth.sign_in') }}</Button>
                </form>

                <Link :href="forgotUrl" class="mt-4 inline-block text-sm text-ink-500 hover:underline">
                    {{ t('auth.forgot_password') }}
                </Link>
            </div>
        </div>
    </PublicLayout>
</template>
