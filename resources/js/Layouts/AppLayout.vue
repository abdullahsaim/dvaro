<script setup>
// Tenant application shell. Wraps all /app/{tenant_slug}/ pages.
// Scaffold shell only — navigation/chrome added per module.
</script>

<template>
    <div class="min-h-screen bg-white text-slate-900 dark:bg-slate-950 dark:text-slate-100 transition-colors">
        <main class="mx-auto w-full max-w-7xl px-4 sm:px-6 lg:px-8">
            <slot />
        </main>
    </div>
</template>
