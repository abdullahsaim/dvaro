<script setup>
// Customer portal profile — email + password + appearance. The display name
// lives on the linked Customer record and is READ-ONLY here: name changes go
// through the rental company (tenant admin), never the portal.
import { computed } from 'vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import CustomerLayout from '@/Layouts/CustomerLayout.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import Input from '@/Components/UI/Input.vue';
import Button from '@/Components/UI/Button.vue';
import PasswordCard from '@/Components/Profile/PasswordCard.vue';
import AppearanceCard from '@/Components/Profile/AppearanceCard.vue';

const props = defineProps({
    user: { type: Object, required: true },
    customerName: { type: String, default: '' },
});

const { t } = useI18n();
const page = usePage();
const base = computed(() => `/portal/${page.props.tenant.slug}/profile`);

const infoForm = useForm({
    email: props.user.email,
});

function saveInfo() {
    infoForm.put(base.value, { preserveScroll: true });
}
</script>

<template>
    <CustomerLayout>
        <Head :title="t('profile.title')" />

        <PageHeader :title="t('profile.title')" :description="t('profile.subtitle')" />

        <div class="max-w-2xl space-y-6">
            <!-- Personal info (name read-only, email editable) -->
            <form
                class="rounded-card border border-ink-200 bg-white p-4 shadow-subtle dark:border-ink-800 dark:bg-ink-900"
                @submit.prevent="saveInfo"
            >
                <h2 class="text-sm font-semibold text-ink-700 dark:text-ink-200">{{ t('profile.personal_info') }}</h2>

                <div class="mt-4 space-y-4">
                    <Input
                        :model-value="customerName"
                        :label="t('profile.name')"
                        :help="t('profile.customer_name_note')"
                        readonly
                        disabled
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

            <PasswordCard :action="`${base}/password`" />

            <AppearanceCard />
        </div>
    </CustomerLayout>
</template>
