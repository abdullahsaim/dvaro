<script setup>
// Customer-portal invitation acceptance. PUBLIC page (web.php route, no bound
// tenant) — slug/name/token/email come as explicit props from the controller.
// Posts the new password to /portal/{slug}/invite/{token}; on success the
// controller creates the CustomerUser, logs them in and redirects to the
// dashboard. FUNCTIONAL ONLY — design pass later.
import { computed } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import PublicLayout from '@/Layouts/PublicLayout.vue';

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

        <div class="mx-auto flex min-h-screen max-w-sm flex-col justify-center px-4">
            <p class="text-sm text-slate-500 dark:text-slate-400">{{ tenantName }}</p>
            <h1 class="text-2xl font-semibold">{{ t('customer.portal.accept_title') }}</h1>
            <p class="mb-6 mt-1 text-sm text-slate-500 dark:text-slate-400">
                {{ t('customer.portal.accept_subtitle') }}
            </p>

            <form class="space-y-4" @submit.prevent="submit">
                <div>
                    <label class="block text-sm font-medium">{{ t('auth.email') }}</label>
                    <input
                        :value="email"
                        type="email"
                        disabled
                        class="mt-1 w-full rounded border border-slate-200 bg-slate-50 px-3 py-2 text-slate-500 dark:border-slate-800 dark:bg-slate-900"
                    />
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
                    {{ t('customer.portal.set_password') }}
                </button>
            </form>
        </div>
    </PublicLayout>
</template>
