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

        <div class="mx-auto flex min-h-screen max-w-md flex-col items-center justify-center px-4 text-center">
            <div class="flex h-14 w-14 items-center justify-center rounded-full bg-green-100 text-2xl text-green-700 dark:bg-green-900/40 dark:text-green-300">
                ✓
            </div>
            <h1 class="mt-6 text-2xl font-semibold">Thank you</h1>
            <p class="mt-2 text-slate-500 dark:text-slate-400">
                We've received your details. {{ tenantName }} will be in touch shortly.
            </p>
        </div>
    </PublicLayout>
</template>
