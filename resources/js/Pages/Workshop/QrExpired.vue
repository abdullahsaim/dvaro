<script setup>
// PUBLIC QR-expired landing. Shown when a scanned QR sticker carries no valid
// signature (a legacy, pre-signing code). No auth, no tenant context, no i18n —
// it is reached cold from a phone camera, exactly like the intake pages.
import { Head } from '@inertiajs/vue3';
import PublicLayout from '@/Layouts/PublicLayout.vue';
</script>

<template>
    <PublicLayout>
        <Head title="QR code expired" />

        <div class="mx-auto flex min-h-screen max-w-md flex-col items-center justify-center px-4 text-center">
            <div class="flex h-14 w-14 items-center justify-center rounded-full bg-amber-100 text-2xl text-amber-700 dark:bg-amber-900/40 dark:text-amber-300">
                !
            </div>
            <h1 class="mt-6 text-2xl font-semibold">This QR code has expired</h1>
            <p class="mt-2 text-slate-500 dark:text-slate-400">
                This QR code is no longer valid. Please contact your rental company
                for an up-to-date code.
            </p>
        </div>
    </PublicLayout>
</template>
