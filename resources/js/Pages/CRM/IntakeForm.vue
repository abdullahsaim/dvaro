<script setup>
// PUBLIC customer intake form. No auth, no tenant context, no i18n (English
// only for now, per spec). Posts to a pre-signed submit URL provided by the
// controller so the signature check passes. Friendly + mobile-responsive.
import { useForm } from '@inertiajs/vue3';
import { Head } from '@inertiajs/vue3';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import Button from '@/Components/UI/Button.vue';
import Input from '@/Components/UI/Input.vue';
import Textarea from '@/Components/UI/Textarea.vue';

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
                <h1 class="text-2xl font-semibold text-ink-900 dark:text-ink-50">{{ tenantName }}</h1>
                <p class="mt-2 text-sm text-ink-500">
                    Please share a few details and we'll be in touch about your rental.
                </p>
            </header>

            <form class="mt-8 grid grid-cols-1 gap-4 sm:grid-cols-2" @submit.prevent="submit">
                <Input v-model="form.name" label="Full name *" :error="form.errors.name" />
                <Input v-model="form.phone" label="Phone *" :error="form.errors.phone" />
                <Input v-model="form.email" type="email" label="Email" :error="form.errors.email" />
                <Input v-model="form.licence_number" label="Driver licence number" :error="form.errors.licence_number" />
                <Input v-model="form.rental_start_date" type="date" label="Preferred start date" :error="form.errors.rental_start_date" />
                <Input v-model="form.rental_duration" label="How long do you need it?" placeholder="e.g. 3 weeks" :error="form.errors.rental_duration" />
                <Input v-model="form.emergency_contact_name" label="Emergency contact name" :error="form.errors.emergency_contact_name" />
                <Input v-model="form.emergency_contact_phone" label="Emergency contact phone" :error="form.errors.emergency_contact_phone" />

                <div class="sm:col-span-2">
                    <Textarea v-model="form.address" :rows="2" label="Address" :error="form.errors.address" />
                </div>
                <div class="sm:col-span-2">
                    <Textarea v-model="form.notes" :rows="3" label="Anything else?" :error="form.errors.notes" />
                </div>

                <div class="sm:col-span-2">
                    <Button type="submit" class="w-full" size="lg" :loading="form.processing">Submit</Button>
                </div>
            </form>
        </div>
    </PublicLayout>
</template>
