<script setup>
// Tenant staff profile — name/email, password, appearance. Own account only.
import { computed } from 'vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import Input from '@/Components/UI/Input.vue';
import Button from '@/Components/UI/Button.vue';
import PasswordCard from '@/Components/Profile/PasswordCard.vue';
import AppearanceCard from '@/Components/Profile/AppearanceCard.vue';

const props = defineProps({
    user: { type: Object, required: true },
});

const { t } = useI18n();
const page = usePage();
const base = computed(() => `/app/${page.props.tenant.slug}/profile`);

const infoForm = useForm({
    name: props.user.name,
    email: props.user.email,
});

function saveInfo() {
    infoForm.put(base.value, { preserveScroll: true });
}
</script>

<template>
    <AppLayout>
        <Head :title="t('profile.title')" />

        <PageHeader :title="t('profile.title')" :description="t('profile.subtitle')" />

        <div class="max-w-2xl space-y-6">
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

                <p class="mt-3 text-xs text-ink-400">{{ t('profile.role') }}: {{ user.role }}</p>

                <Button type="submit" class="mt-4" :loading="infoForm.processing">{{ t('common.save') }}</Button>
            </form>

            <PasswordCard :action="`${base}/password`" />

            <AppearanceCard />
        </div>
    </AppLayout>
</template>
