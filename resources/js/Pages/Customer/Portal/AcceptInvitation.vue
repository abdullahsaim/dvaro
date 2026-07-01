<script setup>
// Customer-portal invitation acceptance. PUBLIC page (web.php route, no bound
// tenant) — slug/name/token/email come as explicit props from the controller.
// Posts the new password to /portal/{slug}/invite/{token}; on success the
// controller creates the CustomerUser, logs them in and redirects to the
// dashboard. Design-system pass.
import { computed } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import Button from '@/Components/UI/Button.vue';
import Input from '@/Components/UI/Input.vue';

const { t } = useI18n();

const props = defineProps({
    tenantSlug: { type: String, required: true },
    tenantName: { type: String, required: true },
    token: { type: String, required: true },
    email: { type: String, required: true },
});

const acceptUrl = computed(() => `/portal/${props.tenantSlug}/invite/${props.token}`);

const form = useForm({
    password: '',
    password_confirmation: '',
});

function submit() {
    form.post(acceptUrl.value, {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
}
</script>

<template>
    <PublicLayout>
        <Head :title="t('customer.portal.accept_title')" />

        <div class="mx-auto flex min-h-[70vh] max-w-sm flex-col justify-center px-4 py-16">
            <div class="rounded-card border border-ink-200 bg-white p-6 shadow-subtle dark:border-ink-800 dark:bg-ink-900">
                <p class="text-sm text-ink-500">{{ tenantName }}</p>
                <h1 class="text-2xl font-semibold text-ink-900 dark:text-ink-50">{{ t('customer.portal.accept_title') }}</h1>
                <p class="mb-6 mt-1 text-sm text-ink-500">{{ t('customer.portal.accept_subtitle') }}</p>

                <form class="space-y-4" @submit.prevent="submit">
                    <Input :model-value="email" type="email" disabled :label="t('auth.email')" />
                    <Input v-model="form.password" type="password" autocomplete="new-password" required :label="t('auth.password')" :error="form.errors.password" />
                    <Input v-model="form.password_confirmation" type="password" autocomplete="new-password" required :label="t('auth.confirm_password')" />

                    <Button type="submit" class="w-full" :loading="form.processing">{{ t('customer.portal.set_password') }}</Button>
                </form>
            </div>
        </div>
    </PublicLayout>
</template>
