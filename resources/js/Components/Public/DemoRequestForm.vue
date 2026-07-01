<script setup>
// Public "request a demo" form. Posts to /demo-request (throttled 3/IP/hour).
// On success the parent flash message is shown and the form resets.
import { useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import Button from '@/Components/UI/Button.vue';
import Input from '@/Components/UI/Input.vue';
import Textarea from '@/Components/UI/Textarea.vue';

const { t } = useI18n();
const page = usePage();

const form = useForm({
    company_name: '',
    contact_name: '',
    email: '',
    phone: '',
    message: '',
});

const success = computed(() => page.props.flash?.success ?? null);

function submit() {
    form.post('/demo-request', {
        preserveScroll: true,
        onSuccess: () => form.reset(),
    });
}
</script>

<template>
    <form class="space-y-4" @submit.prevent="submit">
        <p v-if="success" class="rounded-control bg-success-50 px-4 py-3 text-sm text-success-700 dark:bg-success-900/20 dark:text-success-300">
            {{ success }}
        </p>

        <div class="grid gap-4 sm:grid-cols-2">
            <Input v-model="form.company_name" required :label="t('public.demo.company_name')" :error="form.errors.company_name" />
            <Input v-model="form.contact_name" required :label="t('public.demo.contact_name')" :error="form.errors.contact_name" />
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <Input v-model="form.email" type="email" required :label="t('public.demo.email')" :error="form.errors.email" />
            <Input v-model="form.phone" :label="t('public.demo.phone')" :error="form.errors.phone" />
        </div>

        <Textarea v-model="form.message" :rows="3" :label="t('public.demo.message')" :error="form.errors.message" />

        <Button type="submit" :loading="form.processing">
            {{ form.processing ? t('public.demo.submitting') : t('public.demo.submit') }}
        </Button>
    </form>
</template>
