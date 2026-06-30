<script setup>
// Edit mechanic form (tenant admin). FUNCTIONAL ONLY — design pass later.
// pin/password are left blank: submitting blank keeps the current credentials.
import { computed } from 'vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    mechanic: { type: Object, required: true },
});

const { t } = useI18n();
const page = usePage();
const base = computed(() => `/app/${page.props.tenant.slug}/mechanics`);

const form = useForm({
    name: props.mechanic.name,
    email: props.mechanic.email,
    phone: props.mechanic.phone ?? '',
    pin: '',
    password: '',
    is_active: props.mechanic.is_active,
});

function submit() {
    form.put(`${base.value}/${props.mechanic.id}`);
}
</script>

<template>
    <AppLayout>
        <Head :title="t('mechanic.edit_mechanic')" />

        <div class="py-10">
            <div class="flex items-center justify-between">
                <h1 class="text-2xl font-semibold">{{ t('mechanic.edit_mechanic') }}</h1>
                <Link :href="base" class="text-sm text-slate-500 hover:underline dark:text-slate-400">
                    {{ t('common.back') }}
                </Link>
            </div>

            <form class="mt-6 grid max-w-2xl grid-cols-1 gap-4 sm:grid-cols-2" @submit.prevent="submit">
                <label class="block">
                    <span class="text-sm text-slate-600 dark:text-slate-300">{{ t('mechanic.fields.name') }}</span>
                    <input v-model="form.name" type="text" class="mt-1 w-full rounded border border-slate-300 px-3 py-2 dark:border-slate-700 dark:bg-slate-900" />
                    <span v-if="form.errors.name" class="text-xs text-red-600">{{ form.errors.name }}</span>
                </label>

                <label class="block">
                    <span class="text-sm text-slate-600 dark:text-slate-300">{{ t('mechanic.fields.email') }}</span>
                    <input v-model="form.email" type="email" class="mt-1 w-full rounded border border-slate-300 px-3 py-2 dark:border-slate-700 dark:bg-slate-900" />
                    <span v-if="form.errors.email" class="text-xs text-red-600">{{ form.errors.email }}</span>
                </label>

                <label class="block">
                    <span class="text-sm text-slate-600 dark:text-slate-300">{{ t('mechanic.fields.phone') }}</span>
                    <input v-model="form.phone" type="text" class="mt-1 w-full rounded border border-slate-300 px-3 py-2 dark:border-slate-700 dark:bg-slate-900" />
                    <span v-if="form.errors.phone" class="text-xs text-red-600">{{ form.errors.phone }}</span>
                </label>

                <label class="flex items-center gap-2 sm:col-span-2">
                    <input v-model="form.is_active" type="checkbox" class="rounded border-slate-300 dark:border-slate-700" />
                    <span class="text-sm text-slate-600 dark:text-slate-300">{{ t('mechanic.fields.is_active') }}</span>
                </label>

                <p class="text-xs text-slate-500 dark:text-slate-400 sm:col-span-2">{{ t('mechanic.credentials_hint') }}</p>

                <label class="block">
                    <span class="text-sm text-slate-600 dark:text-slate-300">{{ t('mechanic.fields.pin') }}</span>
                    <input v-model="form.pin" type="text" autocomplete="off" class="mt-1 w-full rounded border border-slate-300 px-3 py-2 dark:border-slate-700 dark:bg-slate-900" />
                    <span v-if="form.errors.pin" class="text-xs text-red-600">{{ form.errors.pin }}</span>
                </label>

                <label class="block">
                    <span class="text-sm text-slate-600 dark:text-slate-300">{{ t('mechanic.fields.password') }}</span>
                    <input v-model="form.password" type="password" autocomplete="new-password" class="mt-1 w-full rounded border border-slate-300 px-3 py-2 dark:border-slate-700 dark:bg-slate-900" />
                    <span v-if="form.errors.password" class="text-xs text-red-600">{{ form.errors.password }}</span>
                </label>

                <div class="sm:col-span-2">
                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="rounded bg-slate-800 px-4 py-2 text-white disabled:opacity-50 dark:bg-slate-200 dark:text-slate-900"
                    >
                        {{ t('common.save') }}
                    </button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
