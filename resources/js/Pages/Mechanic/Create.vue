<script setup>
// New mechanic form (tenant admin). Design-system pass.
import { computed } from 'vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import Button from '@/Components/UI/Button.vue';
import Input from '@/Components/UI/Input.vue';

const { t } = useI18n();
const page = usePage();
const base = computed(() => `/app/${page.props.tenant.slug}/mechanics`);

const checkbox = 'h-4 w-4 rounded border-ink-300 accent-ink-900 dark:border-ink-700 dark:accent-ink-100';

const form = useForm({
    name: '',
    email: '',
    phone: '',
    pin: '',
    password: '',
    is_active: true,
});

function submit() {
    form.post(base.value);
}
</script>

<template>
    <AppLayout>
        <Head :title="t('mechanic.new_mechanic')" />

        <PageHeader :title="t('mechanic.new_mechanic')">
            <template #actions>
                <Button variant="ghost" @click="router.visit(base)">{{ t('common.back') }}</Button>
            </template>
        </PageHeader>

        <form class="grid max-w-2xl grid-cols-1 gap-4 sm:grid-cols-2" @submit.prevent="submit">
            <Input v-model="form.name" :label="t('mechanic.fields.name')" :error="form.errors.name" />
            <Input v-model="form.email" type="email" :label="t('mechanic.fields.email')" :error="form.errors.email" />
            <Input v-model="form.phone" :label="t('mechanic.fields.phone')" :error="form.errors.phone" />

            <label class="flex items-center gap-2 sm:col-span-2">
                <input v-model="form.is_active" type="checkbox" :class="checkbox" />
                <span class="text-sm text-ink-600 dark:text-ink-300">{{ t('mechanic.fields.is_active') }}</span>
            </label>

            <p class="text-xs text-ink-500 sm:col-span-2">{{ t('mechanic.credentials_hint') }}</p>

            <Input v-model="form.pin" autocomplete="off" :label="t('mechanic.fields.pin')" :error="form.errors.pin" />
            <Input v-model="form.password" type="password" autocomplete="new-password" :label="t('mechanic.fields.password')" :error="form.errors.password" />

            <div class="sm:col-span-2">
                <Button type="submit" :loading="form.processing">{{ t('common.save') }}</Button>
            </div>
        </form>
    </AppLayout>
</template>
