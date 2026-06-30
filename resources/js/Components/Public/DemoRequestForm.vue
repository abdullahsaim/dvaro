<script setup>
// Public "request a demo" form. Posts to /demo-request (throttled 3/IP/hour).
// On success the parent flash message is shown and the form resets.
import { useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

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
        <p v-if="success" class="rounded-lg bg-emerald-50 px-4 py-3 text-sm text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300">
            {{ success }}
        </p>

        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label for="company_name" class="block text-sm font-medium">{{ t('public.demo.company_name') }}</label>
                <input id="company_name" v-model="form.company_name" type="text" required
                    class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 dark:border-slate-700 dark:bg-slate-900" />
                <p v-if="form.errors.company_name" class="mt-1 text-sm text-red-600">{{ form.errors.company_name }}</p>
            </div>
            <div>
                <label for="contact_name" class="block text-sm font-medium">{{ t('public.demo.contact_name') }}</label>
                <input id="contact_name" v-model="form.contact_name" type="text" required
                    class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 dark:border-slate-700 dark:bg-slate-900" />
                <p v-if="form.errors.contact_name" class="mt-1 text-sm text-red-600">{{ form.errors.contact_name }}</p>
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label for="demo_email" class="block text-sm font-medium">{{ t('public.demo.email') }}</label>
                <input id="demo_email" v-model="form.email" type="email" required
                    class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 dark:border-slate-700 dark:bg-slate-900" />
                <p v-if="form.errors.email" class="mt-1 text-sm text-red-600">{{ form.errors.email }}</p>
            </div>
            <div>
                <label for="demo_phone" class="block text-sm font-medium">{{ t('public.demo.phone') }}</label>
                <input id="demo_phone" v-model="form.phone" type="text"
                    class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 dark:border-slate-700 dark:bg-slate-900" />
                <p v-if="form.errors.phone" class="mt-1 text-sm text-red-600">{{ form.errors.phone }}</p>
            </div>
        </div>

        <div>
            <label for="demo_message" class="block text-sm font-medium">{{ t('public.demo.message') }}</label>
            <textarea id="demo_message" v-model="form.message" rows="3"
                class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 dark:border-slate-700 dark:bg-slate-900"></textarea>
            <p v-if="form.errors.message" class="mt-1 text-sm text-red-600">{{ form.errors.message }}</p>
        </div>

        <button type="submit" :disabled="form.processing"
            class="w-full rounded-lg bg-indigo-600 px-4 py-2.5 font-semibold text-white transition hover:bg-indigo-500 disabled:opacity-50 sm:w-auto">
            {{ form.processing ? t('public.demo.submitting') : t('public.demo.submit') }}
        </button>
    </form>
</template>
