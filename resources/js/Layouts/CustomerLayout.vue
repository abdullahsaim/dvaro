<script setup>
// Customer portal shell. Wraps all /portal/{tenant_slug}/ pages.
// Scaffold shell only — navigation/chrome added per module.
</script>

<template>
    <div class="min-h-screen bg-white text-slate-900 dark:bg-slate-950 dark:text-slate-100 transition-colors">
        <main class="mx-auto w-full max-w-5xl px-4 sm:px-6 lg:px-8">
            <slot />
        </main>
    </div>
</template>
