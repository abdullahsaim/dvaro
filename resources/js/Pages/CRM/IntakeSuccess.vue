<script setup>
// PUBLIC intake success page. No auth, no tenant context, no i18n.
import { Head } from '@inertiajs/vue3';
import PublicLayout from '@/Layouts/PublicLayout.vue';

defineProps({
    tenantName: { type: String, required: true },
});
</script>

<template>
    <PublicLayout>
        <Head title="Thank you" />

        <div class="mx-auto flex min-h-[70vh] max-w-md flex-col items-center justify-center px-4 py-16 text-center">
            <div class="flex h-14 w-14 items-center justify-center rounded-full bg-success-100 text-2xl text-success-700 dark:bg-success-900/40 dark:text-success-300">
                ✓
            </div>
            <h1 class="mt-6 text-2xl font-semibold text-ink-900 dark:text-ink-50">Thank you</h1>
            <p class="mt-2 text-ink-500">
                We've received your details. {{ tenantName }} will be in touch shortly.
            </p>
        </div>
    </PublicLayout>
</template>
