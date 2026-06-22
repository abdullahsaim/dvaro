<script setup>
// PUBLIC customer intake form. No auth, no tenant context, no i18n (English
// only for now, per spec). Posts to a pre-signed submit URL provided by the
// controller so the signature check passes. Friendly + mobile-responsive.
import { useForm } from '@inertiajs/vue3';
import { Head } from '@inertiajs/vue3';
import PublicLayout from '@/Layouts/PublicLayout.vue';

const props = defineProps({
    tenantName: { type: String, required: true },
    submitUrl: { type: String, required: true },
    lead: { type: Object, required: true }, // prefill from what staff captured
});

const form = useForm({
    name: props.lead.name ?? '',
    phone: props.lead.phone ?? '',
    email: props.lead.email ?? '',
    address: props.lead.address ?? '',
    licence_number: props.lead.licence_number ?? '',
    emergency_contact_name: props.lead.emergency_contact_name ?? '',
    emergency_contact_phone: props.lead.emergency_contact_phone ?? '',
    rental_start_date: props.lead.rental_start_date ?? '',
    rental_duration: props.lead.rental_duration ?? '',
    notes: props.lead.notes ?? '',
});

function submit() {
    form.post(props.submitUrl);
}
</script>

<template>
    <PublicLayout>
        <Head title="Rental enquiry" />

        <div class="mx-auto max-w-xl px-4 py-12">
            <header class="text-center">
                <h1 class="text-2xl font-semibold">{{ tenantName }}</h1>
                <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">
                    Please share a few details and we'll be in touch about your rental.
                </p>
            </header>

            <form class="mt-8 grid grid-cols-1 gap-4 sm:grid-cols-2" @submit.prevent="submit">
                <label class="block">
                    <span class="text-sm text-slate-600 dark:text-slate-300">Full name *</span>
                    <input v-model="form.name" type="text" class="mt-1 w-full rounded border border-slate-300 px-3 py-2 dark:border-slate-700 dark:bg-slate-900" />
                    <span v-if="form.errors.name" class="text-xs text-red-600">{{ form.errors.name }}</span>
                </label>

                <label class="block">
                    <span class="text-sm text-slate-600 dark:text-slate-300">Phone *</span>
                    <input v-model="form.phone" type="text" class="mt-1 w-full rounded border border-slate-300 px-3 py-2 dark:border-slate-700 dark:bg-slate-900" />
                    <span v-if="form.errors.phone" class="text-xs text-red-600">{{ form.errors.phone }}</span>
                </label>

                <label class="block">
                    <span class="text-sm text-slate-600 dark:text-slate-300">Email</span>
                    <input v-model="form.email" type="email" class="mt-1 w-full rounded border border-slate-300 px-3 py-2 dark:border-slate-700 dark:bg-slate-900" />
                    <span v-if="form.errors.email" class="text-xs text-red-600">{{ form.errors.email }}</span>
                </label>

                <label class="block">
                    <span class="text-sm text-slate-600 dark:text-slate-300">Driver licence number</span>
                    <input v-model="form.licence_number" type="text" class="mt-1 w-full rounded border border-slate-300 px-3 py-2 dark:border-slate-700 dark:bg-slate-900" />
                    <span v-if="form.errors.licence_number" class="text-xs text-red-600">{{ form.errors.licence_number }}</span>
                </label>

                <label class="block">
                    <span class="text-sm text-slate-600 dark:text-slate-300">Preferred start date</span>
                    <input v-model="form.rental_start_date" type="date" class="mt-1 w-full rounded border border-slate-300 px-3 py-2 dark:border-slate-700 dark:bg-slate-900" />
                    <span v-if="form.errors.rental_start_date" class="text-xs text-red-600">{{ form.errors.rental_start_date }}</span>
                </label>

                <label class="block">
                    <span class="text-sm text-slate-600 dark:text-slate-300">How long do you need it?</span>
                    <input v-model="form.rental_duration" type="text" placeholder="e.g. 3 weeks" class="mt-1 w-full rounded border border-slate-300 px-3 py-2 dark:border-slate-700 dark:bg-slate-900" />
                    <span v-if="form.errors.rental_duration" class="text-xs text-red-600">{{ form.errors.rental_duration }}</span>
                </label>

                <label class="block">
                    <span class="text-sm text-slate-600 dark:text-slate-300">Emergency contact name</span>
                    <input v-model="form.emergency_contact_name" type="text" class="mt-1 w-full rounded border border-slate-300 px-3 py-2 dark:border-slate-700 dark:bg-slate-900" />
                    <span v-if="form.errors.emergency_contact_name" class="text-xs text-red-600">{{ form.errors.emergency_contact_name }}</span>
                </label>

                <label class="block">
                    <span class="text-sm text-slate-600 dark:text-slate-300">Emergency contact phone</span>
                    <input v-model="form.emergency_contact_phone" type="text" class="mt-1 w-full rounded border border-slate-300 px-3 py-2 dark:border-slate-700 dark:bg-slate-900" />
                    <span v-if="form.errors.emergency_contact_phone" class="text-xs text-red-600">{{ form.errors.emergency_contact_phone }}</span>
                </label>

                <label class="block sm:col-span-2">
                    <span class="text-sm text-slate-600 dark:text-slate-300">Address</span>
                    <textarea v-model="form.address" rows="2" class="mt-1 w-full rounded border border-slate-300 px-3 py-2 dark:border-slate-700 dark:bg-slate-900" />
                    <span v-if="form.errors.address" class="text-xs text-red-600">{{ form.errors.address }}</span>
                </label>

                <label class="block sm:col-span-2">
                    <span class="text-sm text-slate-600 dark:text-slate-300">Anything else?</span>
                    <textarea v-model="form.notes" rows="3" class="mt-1 w-full rounded border border-slate-300 px-3 py-2 dark:border-slate-700 dark:bg-slate-900" />
                    <span v-if="form.errors.notes" class="text-xs text-red-600">{{ form.errors.notes }}</span>
                </label>

                <div class="sm:col-span-2">
                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="w-full rounded bg-slate-800 px-4 py-3 text-white disabled:opacity-50 dark:bg-slate-200 dark:text-slate-900"
                    >
                        Submit
                    </button>
                </div>
            </form>
        </div>
    </PublicLayout>
</template>
