<script setup>
// Mechanic-portal login. Design-system pass.
// Two modes: full password, or a quick numeric PIN. Posts to
// /mechanic/{tenant_slug}/login; tenant slug comes from shared props.
import { computed, ref } from 'vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import Button from '@/Components/UI/Button.vue';
import Input from '@/Components/UI/Input.vue';

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

        <div class="mx-auto flex min-h-[70vh] max-w-sm flex-col justify-center px-4 py-16">
            <div class="rounded-card border border-ink-200 bg-white p-6 shadow-subtle dark:border-ink-800 dark:bg-ink-900">
                <h1 class="mb-1 text-2xl font-semibold text-ink-900 dark:text-ink-50">{{ t('workshop.portal_title') }}</h1>
                <p class="mb-6 text-sm text-ink-500">{{ t('workshop.sign_in') }}</p>

                <form class="space-y-4" @submit.prevent="submit">
                    <Input v-model="form.email" type="email" autocomplete="username" required :label="t('auth.email')" :error="form.errors.email" />

                    <Input
                        v-if="!usePin"
                        v-model="form.password"
                        type="password"
                        autocomplete="current-password"
                        :label="t('auth.password')"
                        :error="form.errors.password"
                    />
                    <Input
                        v-else
                        v-model="form.pin"
                        type="password"
                        inputmode="numeric"
                        autocomplete="off"
                        :label="t('auth.pin')"
                        :error="form.errors.pin"
                    />

                    <Button type="submit" class="w-full" :loading="form.processing">{{ t('workshop.sign_in') }}</Button>

                    <button
                        type="button"
                        class="w-full text-center text-sm text-ink-500 hover:underline"
                        @click="usePin = !usePin"
                    >
                        {{ usePin ? t('auth.use_password') : t('auth.use_pin') }}
                    </button>
                </form>

                <Link :href="forgotUrl" class="mt-4 inline-block text-sm text-ink-500 hover:underline">
                    {{ t('auth.forgot_password') }}
                </Link>
            </div>
        </div>
    </PublicLayout>
</template>
