<script setup>
// Change-password card shared by all four guard profile pages. Posts to the
// guard's own profile/password endpoint; the server verifies the current
// credential via Hash::check before accepting the new password. The mechanic
// page overrides the current-credential label ("Current password or PIN").
import { useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import Input from '@/Components/UI/Input.vue';
import Button from '@/Components/UI/Button.vue';

const props = defineProps({
    action: { type: String, required: true },
    currentLabel: { type: String, default: '' },
});

const { t } = useI18n();

const form = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
});

function submit() {
    form.put(props.action, {
        preserveScroll: true,
        onSuccess: () => form.reset(),
    });
}
</script>

<template>
    <form
        class="rounded-card border border-ink-200 bg-white p-4 shadow-subtle dark:border-ink-800 dark:bg-ink-900"
        @submit.prevent="submit"
    >
        <h2 class="text-sm font-semibold text-ink-700 dark:text-ink-200">{{ t('profile.change_password') }}</h2>
        <p class="mt-1 text-xs text-ink-400">{{ t('profile.change_password_hint') }}</p>

        <div class="mt-4 space-y-4">
            <Input
                v-model="form.current_password"
                type="password"
                autocomplete="current-password"
                :label="currentLabel || t('profile.current_password')"
                :error="form.errors.current_password"
                required
            />
            <Input
                v-model="form.password"
                type="password"
                autocomplete="new-password"
                :label="t('profile.new_password')"
                :error="form.errors.password"
                required
            />
            <Input
                v-model="form.password_confirmation"
                type="password"
                autocomplete="new-password"
                :label="t('profile.confirm_password')"
                :error="form.errors.password_confirmation"
                required
            />
        </div>

        <Button type="submit" class="mt-4" :loading="form.processing">{{ t('profile.update_password') }}</Button>
    </form>
</template>
