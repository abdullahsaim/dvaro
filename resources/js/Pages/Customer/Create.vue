<script setup>
// New customer form. FUNCTIONAL ONLY — design pass later.
import { computed } from 'vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import AppLayout from '@/Layouts/AppLayout.vue';

const { t } = useI18n();
const page = usePage();
const base = computed(() => `/app/${page.props.tenant.slug}/customers`);

const form = useForm({
    name: '',
    email: '',
    phone: '',
    licence_number: '',
    licence_expiry: '',
    passport_number: '',
    date_of_birth: '',
    address: '',
    emergency_contact_name: '',
    emergency_contact_phone: '',
    risk_notes: '',
});

function submit() {
    form.post(base.value);
}
</script>

<template>
    <AppLayout>
        <Head :title="t('customer.new_customer')" />

        <div class="py-10">
            <div class="flex items-center justify-between">
                <h1 class="text-2xl font-semibold">{{ t('customer.new_customer') }}</h1>
                <Link :href="base" class="text-sm text-slate-500 hover:underline dark:text-slate-400">
                    {{ t('common.back') }}
                </Link>
            </div>

            <form class="mt-6 grid max-w-2xl grid-cols-1 gap-4 sm:grid-cols-2" @submit.prevent="submit">
                <label class="block">
                    <span class="text-sm text-slate-600 dark:text-slate-300">{{ t('customer.fields.name') }}</span>
                    <input v-model="form.name" type="text" class="mt-1 w-full rounded border border-slate-300 px-3 py-2 dark:border-slate-700 dark:bg-slate-900" />
                    <span v-if="form.errors.name" class="text-xs text-red-600">{{ form.errors.name }}</span>
                </label>

                <label class="block">
                    <span class="text-sm text-slate-600 dark:text-slate-300">{{ t('customer.fields.email') }}</span>
                    <input v-model="form.email" type="email" class="mt-1 w-full rounded border border-slate-300 px-3 py-2 dark:border-slate-700 dark:bg-slate-900" />
                    <span v-if="form.errors.email" class="text-xs text-red-600">{{ form.errors.email }}</span>
                </label>

                <label class="block">
                    <span class="text-sm text-slate-600 dark:text-slate-300">{{ t('customer.fields.phone') }}</span>
                    <input v-model="form.phone" type="text" class="mt-1 w-full rounded border border-slate-300 px-3 py-2 dark:border-slate-700 dark:bg-slate-900" />
                    <span v-if="form.errors.phone" class="text-xs text-red-600">{{ form.errors.phone }}</span>
                </label>

                <label class="block">
                    <span class="text-sm text-slate-600 dark:text-slate-300">{{ t('customer.fields.date_of_birth') }}</span>
                    <input v-model="form.date_of_birth" type="date" class="mt-1 w-full rounded border border-slate-300 px-3 py-2 dark:border-slate-700 dark:bg-slate-900" />
                    <span v-if="form.errors.date_of_birth" class="text-xs text-red-600">{{ form.errors.date_of_birth }}</span>
                </label>

                <label class="block">
                    <span class="text-sm text-slate-600 dark:text-slate-300">{{ t('customer.fields.licence_number') }}</span>
                    <input v-model="form.licence_number" type="text" class="mt-1 w-full rounded border border-slate-300 px-3 py-2 dark:border-slate-700 dark:bg-slate-900" />
                    <span v-if="form.errors.licence_number" class="text-xs text-red-600">{{ form.errors.licence_number }}</span>
                </label>

                <label class="block">
                    <span class="text-sm text-slate-600 dark:text-slate-300">{{ t('customer.fields.licence_expiry') }}</span>
                    <input v-model="form.licence_expiry" type="date" class="mt-1 w-full rounded border border-slate-300 px-3 py-2 dark:border-slate-700 dark:bg-slate-900" />
                    <span v-if="form.errors.licence_expiry" class="text-xs text-red-600">{{ form.errors.licence_expiry }}</span>
                </label>

                <label class="block">
                    <span class="text-sm text-slate-600 dark:text-slate-300">{{ t('customer.fields.passport_number') }}</span>
                    <input v-model="form.passport_number" type="text" class="mt-1 w-full rounded border border-slate-300 px-3 py-2 dark:border-slate-700 dark:bg-slate-900" />
                    <span v-if="form.errors.passport_number" class="text-xs text-red-600">{{ form.errors.passport_number }}</span>
                </label>

                <label class="block">
                    <span class="text-sm text-slate-600 dark:text-slate-300">{{ t('customer.fields.emergency_contact_name') }}</span>
                    <input v-model="form.emergency_contact_name" type="text" class="mt-1 w-full rounded border border-slate-300 px-3 py-2 dark:border-slate-700 dark:bg-slate-900" />
                    <span v-if="form.errors.emergency_contact_name" class="text-xs text-red-600">{{ form.errors.emergency_contact_name }}</span>
                </label>

                <label class="block">
                    <span class="text-sm text-slate-600 dark:text-slate-300">{{ t('customer.fields.emergency_contact_phone') }}</span>
                    <input v-model="form.emergency_contact_phone" type="text" class="mt-1 w-full rounded border border-slate-300 px-3 py-2 dark:border-slate-700 dark:bg-slate-900" />
                    <span v-if="form.errors.emergency_contact_phone" class="text-xs text-red-600">{{ form.errors.emergency_contact_phone }}</span>
                </label>

                <label class="block sm:col-span-2">
                    <span class="text-sm text-slate-600 dark:text-slate-300">{{ t('customer.fields.address') }}</span>
                    <textarea v-model="form.address" rows="2" class="mt-1 w-full rounded border border-slate-300 px-3 py-2 dark:border-slate-700 dark:bg-slate-900" />
                    <span v-if="form.errors.address" class="text-xs text-red-600">{{ form.errors.address }}</span>
                </label>

                <label class="block sm:col-span-2">
                    <span class="text-sm text-slate-600 dark:text-slate-300">{{ t('customer.fields.risk_notes') }}</span>
                    <textarea v-model="form.risk_notes" rows="3" class="mt-1 w-full rounded border border-slate-300 px-3 py-2 dark:border-slate-700 dark:bg-slate-900" />
                    <span v-if="form.errors.risk_notes" class="text-xs text-red-600">{{ form.errors.risk_notes }}</span>
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
