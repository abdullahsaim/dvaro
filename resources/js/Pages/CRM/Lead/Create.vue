<script setup>
// New lead form. FUNCTIONAL ONLY — design pass later.
// After creation the controller re-renders this page with `generatedLink`; we
// reveal the shareable signed intake link + a copy button. Sending is manual
// for now (Notification module is a later session).
import { computed, ref } from 'vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    generatedLink: { type: String, default: null },
});

const { t } = useI18n();
const page = usePage();
const base = computed(() => `/app/${page.props.tenant.slug}/leads`);

const form = useForm({
    name: '',
    email: '',
    phone: '',
    address: '',
    licence_number: '',
    emergency_contact_name: '',
    emergency_contact_phone: '',
    rental_start_date: '',
    rental_duration: '',
    notes: '',
    token_expires_at: '',
});

function submit() {
    // preserveState:false so the fresh `generatedLink` prop replaces page state.
    form.post(base.value, { preserveState: false });
}

const copied = ref(false);
async function copyLink() {
    try {
        await navigator.clipboard.writeText(props.generatedLink);
        copied.value = true;
        setTimeout(() => (copied.value = false), 2000);
    } catch {
        // Clipboard API unavailable (insecure context) — user can select manually.
    }
}
</script>

<template>
    <AppLayout>
        <Head :title="t('crm.new_lead')" />

        <div class="py-10">
            <div class="flex items-center justify-between">
                <h1 class="text-2xl font-semibold">{{ t('crm.new_lead') }}</h1>
                <Link :href="base" class="text-sm text-slate-500 hover:underline dark:text-slate-400">
                    {{ t('common.back') }}
                </Link>
            </div>

            <!-- Generated-link panel (shown after a successful create) -->
            <div
                v-if="generatedLink"
                class="mt-6 max-w-2xl rounded border border-green-200 bg-green-50 p-4 dark:border-green-900 dark:bg-green-900/30"
            >
                <h2 class="font-medium text-green-800 dark:text-green-300">{{ t('crm.created_heading') }}</h2>
                <p class="mt-1 text-sm text-green-700 dark:text-green-400">{{ t('crm.intake_link_hint') }}</p>
                <div class="mt-3 flex gap-2">
                    <input
                        :value="generatedLink"
                        readonly
                        class="w-full rounded border border-green-300 bg-white px-3 py-2 text-sm dark:border-green-800 dark:bg-slate-900"
                        @focus="$event.target.select()"
                    />
                    <button
                        type="button"
                        class="shrink-0 rounded bg-green-700 px-3 py-2 text-sm text-white hover:bg-green-800"
                        @click="copyLink"
                    >
                        {{ copied ? t('crm.copied') : t('crm.copy') }}
                    </button>
                </div>
                <Link :href="`${base}/create`" class="mt-3 inline-block text-sm text-green-700 hover:underline dark:text-green-400">
                    {{ t('crm.create_another') }}
                </Link>
            </div>

            <form v-else class="mt-6 grid max-w-2xl grid-cols-1 gap-4 sm:grid-cols-2" @submit.prevent="submit">
                <label class="block">
                    <span class="text-sm text-slate-600 dark:text-slate-300">{{ t('crm.fields.name') }}</span>
                    <input v-model="form.name" type="text" class="mt-1 w-full rounded border border-slate-300 px-3 py-2 dark:border-slate-700 dark:bg-slate-900" />
                    <span v-if="form.errors.name" class="text-xs text-red-600">{{ form.errors.name }}</span>
                </label>

                <label class="block">
                    <span class="text-sm text-slate-600 dark:text-slate-300">{{ t('crm.fields.email') }}</span>
                    <input v-model="form.email" type="email" class="mt-1 w-full rounded border border-slate-300 px-3 py-2 dark:border-slate-700 dark:bg-slate-900" />
                    <span v-if="form.errors.email" class="text-xs text-red-600">{{ form.errors.email }}</span>
                </label>

                <label class="block">
                    <span class="text-sm text-slate-600 dark:text-slate-300">{{ t('crm.fields.phone') }}</span>
                    <input v-model="form.phone" type="text" class="mt-1 w-full rounded border border-slate-300 px-3 py-2 dark:border-slate-700 dark:bg-slate-900" />
                    <span v-if="form.errors.phone" class="text-xs text-red-600">{{ form.errors.phone }}</span>
                </label>

                <label class="block">
                    <span class="text-sm text-slate-600 dark:text-slate-300">{{ t('crm.fields.token_expires_at') }}</span>
                    <input v-model="form.token_expires_at" type="date" class="mt-1 w-full rounded border border-slate-300 px-3 py-2 dark:border-slate-700 dark:bg-slate-900" />
                    <span v-if="form.errors.token_expires_at" class="text-xs text-red-600">{{ form.errors.token_expires_at }}</span>
                </label>

                <label class="block">
                    <span class="text-sm text-slate-600 dark:text-slate-300">{{ t('crm.fields.licence_number') }}</span>
                    <input v-model="form.licence_number" type="text" class="mt-1 w-full rounded border border-slate-300 px-3 py-2 dark:border-slate-700 dark:bg-slate-900" />
                    <span v-if="form.errors.licence_number" class="text-xs text-red-600">{{ form.errors.licence_number }}</span>
                </label>

                <label class="block">
                    <span class="text-sm text-slate-600 dark:text-slate-300">{{ t('crm.fields.rental_start_date') }}</span>
                    <input v-model="form.rental_start_date" type="date" class="mt-1 w-full rounded border border-slate-300 px-3 py-2 dark:border-slate-700 dark:bg-slate-900" />
                    <span v-if="form.errors.rental_start_date" class="text-xs text-red-600">{{ form.errors.rental_start_date }}</span>
                </label>

                <label class="block">
                    <span class="text-sm text-slate-600 dark:text-slate-300">{{ t('crm.fields.rental_duration') }}</span>
                    <input v-model="form.rental_duration" type="text" class="mt-1 w-full rounded border border-slate-300 px-3 py-2 dark:border-slate-700 dark:bg-slate-900" />
                    <span v-if="form.errors.rental_duration" class="text-xs text-red-600">{{ form.errors.rental_duration }}</span>
                </label>

                <label class="block">
                    <span class="text-sm text-slate-600 dark:text-slate-300">{{ t('crm.fields.emergency_contact_name') }}</span>
                    <input v-model="form.emergency_contact_name" type="text" class="mt-1 w-full rounded border border-slate-300 px-3 py-2 dark:border-slate-700 dark:bg-slate-900" />
                    <span v-if="form.errors.emergency_contact_name" class="text-xs text-red-600">{{ form.errors.emergency_contact_name }}</span>
                </label>

                <label class="block">
                    <span class="text-sm text-slate-600 dark:text-slate-300">{{ t('crm.fields.emergency_contact_phone') }}</span>
                    <input v-model="form.emergency_contact_phone" type="text" class="mt-1 w-full rounded border border-slate-300 px-3 py-2 dark:border-slate-700 dark:bg-slate-900" />
                    <span v-if="form.errors.emergency_contact_phone" class="text-xs text-red-600">{{ form.errors.emergency_contact_phone }}</span>
                </label>

                <label class="block sm:col-span-2">
                    <span class="text-sm text-slate-600 dark:text-slate-300">{{ t('crm.fields.address') }}</span>
                    <textarea v-model="form.address" rows="2" class="mt-1 w-full rounded border border-slate-300 px-3 py-2 dark:border-slate-700 dark:bg-slate-900" />
                    <span v-if="form.errors.address" class="text-xs text-red-600">{{ form.errors.address }}</span>
                </label>

                <label class="block sm:col-span-2">
                    <span class="text-sm text-slate-600 dark:text-slate-300">{{ t('crm.fields.notes') }}</span>
                    <textarea v-model="form.notes" rows="3" class="mt-1 w-full rounded border border-slate-300 px-3 py-2 dark:border-slate-700 dark:bg-slate-900" />
                    <span v-if="form.errors.notes" class="text-xs text-red-600">{{ form.errors.notes }}</span>
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
