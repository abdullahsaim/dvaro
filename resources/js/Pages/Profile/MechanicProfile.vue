<script setup>
// Mechanic portal profile — name/email, password, PIN, appearance. Mobile-first
// like the rest of the portal. A mechanic may be PIN-only (no password), so
// identity checks accept either credential; the label reflects what they have.
import { computed } from 'vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import MechanicLayout from '@/Layouts/MechanicLayout.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import Input from '@/Components/UI/Input.vue';
import Button from '@/Components/UI/Button.vue';
import PasswordCard from '@/Components/Profile/PasswordCard.vue';
import AppearanceCard from '@/Components/Profile/AppearanceCard.vue';

const props = defineProps({
    user: { type: Object, required: true },
    hasPassword: { type: Boolean, default: false },
    hasPin: { type: Boolean, default: false },
});

const { t } = useI18n();
const page = usePage();
const base = computed(() => `/mechanic/${page.props.tenant.slug}/profile`);

// What proves identity: with a PIN on file either credential works, so the
// broader label; password-only mechanics see the plain password label.
const credentialLabel = computed(() =>
    props.hasPin ? t('profile.current_credential') : t('profile.current_password'),
);

const infoForm = useForm({
    name: props.user.name,
    email: props.user.email,
});

const pinForm = useForm({
    current_password: '',
    pin: '',
    pin_confirmation: '',
});

function saveInfo() {
    infoForm.put(base.value, { preserveScroll: true });
}

function savePin() {
    pinForm.put(`${base.value}/pin`, {
        preserveScroll: true,
        onSuccess: () => pinForm.reset(),
    });
}
</script>

<template>
    <MechanicLayout>
        <Head :title="t('profile.title')" />

        <PageHeader :title="t('profile.title')" :description="t('profile.subtitle')" />

        <div class="space-y-6">
            <!-- Personal info -->
            <form
                class="rounded-card border border-ink-200 bg-white p-4 shadow-subtle dark:border-ink-800 dark:bg-ink-900"
                @submit.prevent="saveInfo"
            >
                <h2 class="text-sm font-semibold text-ink-700 dark:text-ink-200">{{ t('profile.personal_info') }}</h2>
                <p class="mt-1 text-xs text-ink-400">{{ t('profile.personal_info_hint') }}</p>

                <div class="mt-4 space-y-4">
                    <Input
                        v-model="infoForm.name"
                        :label="t('profile.name')"
                        :error="infoForm.errors.name"
                        autocomplete="name"
                        required
                    />
                    <Input
                        v-model="infoForm.email"
                        type="email"
                        :label="t('profile.email')"
                        :error="infoForm.errors.email"
                        autocomplete="email"
                        required
                    />
                </div>

                <Button type="submit" class="mt-4" :loading="infoForm.processing">{{ t('common.save') }}</Button>
            </form>

            <PasswordCard :action="`${base}/password`" :current-label="credentialLabel" />

            <!-- PIN -->
            <form
                class="rounded-card border border-ink-200 bg-white p-4 shadow-subtle dark:border-ink-800 dark:bg-ink-900"
                @submit.prevent="savePin"
            >
                <h2 class="text-sm font-semibold text-ink-700 dark:text-ink-200">{{ t('profile.change_pin') }}</h2>
                <p class="mt-1 text-xs text-ink-400">{{ t('profile.change_pin_hint') }}</p>

                <div class="mt-4 space-y-4">
                    <Input
                        v-model="pinForm.current_password"
                        type="password"
                        autocomplete="current-password"
                        :label="credentialLabel"
                        :error="pinForm.errors.current_password"
                        required
                    />
                    <Input
                        v-model="pinForm.pin"
                        type="password"
                        inputmode="numeric"
                        :label="t('profile.new_pin')"
                        :error="pinForm.errors.pin"
                        required
                    />
                    <Input
                        v-model="pinForm.pin_confirmation"
                        type="password"
                        inputmode="numeric"
                        :label="t('profile.confirm_pin')"
                        :error="pinForm.errors.pin_confirmation"
                        required
                    />
                </div>

                <Button type="submit" class="mt-4" :loading="pinForm.processing">{{ t('profile.update_pin') }}</Button>
            </form>

            <AppearanceCard />
        </div>
    </MechanicLayout>
</template>
