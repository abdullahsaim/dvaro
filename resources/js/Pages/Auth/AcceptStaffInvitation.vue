<script setup>
// PUBLIC "set your password" page for an invited staff member. No account
// exists yet — the emailed token is the only credential. On success the server
// signs them in and sends them to the dashboard.
import { Head, useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import Button from '@/Components/UI/Button.vue';
import Input from '@/Components/UI/Input.vue';

const props = defineProps({
    valid: { type: Boolean, default: false },
    status: { type: String, default: null }, // pending | accepted | revoked | expired
    name: { type: String, default: null },
    email: { type: String, default: null },
    companyName: { type: String, required: true },
    token: { type: String, required: true },
});

const { t } = useI18n();

const form = useForm({ password: '', password_confirmation: '' });

function submit() {
    form.post(window.location.pathname, {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
}
</script>

<template>
    <PublicLayout>
        <Head :title="t('settings.staff.accept_title')" />

        <div class="mx-auto flex min-h-[70vh] max-w-md flex-col justify-center px-4 py-12">
            <div class="rounded-card border border-ink-200 bg-white p-6 shadow-subtle dark:border-ink-800 dark:bg-ink-900">
                <template v-if="valid">
                    <h1 class="text-xl font-semibold text-ink-900 dark:text-ink-50">
                        {{ t('settings.staff.accept_heading', { company: companyName }) }}
                    </h1>
                    <p class="mt-2 text-sm text-ink-500">{{ t('settings.staff.accept_body', { name, email }) }}</p>

                    <form class="mt-6 space-y-4" @submit.prevent="submit">
                        <Input
                            v-model="form.password"
                            type="password"
                            required
                            autocomplete="new-password"
                            :label="t('auth.new_password')"
                            :error="form.errors.password"
                        />
                        <Input
                            v-model="form.password_confirmation"
                            type="password"
                            required
                            autocomplete="new-password"
                            :label="t('auth.confirm_password')"
                        />
                        <Button type="submit" class="w-full" size="lg" :loading="form.processing">
                            {{ t('settings.staff.accept_submit') }}
                        </Button>
                    </form>
                </template>

                <template v-else>
                    <h1 class="text-xl font-semibold text-ink-900 dark:text-ink-50">{{ t('settings.staff.invite_unavailable') }}</h1>
                    <p class="mt-2 text-sm text-ink-500">{{ t(`settings.staff.invite_unavailable_${status ?? 'missing'}`) }}</p>
                </template>
            </div>
        </div>
    </PublicLayout>
</template>
