<script setup>
// Super admin panel shell. Wraps all /superadmin/ pages.
// Scaffold shell only — navigation/chrome added per module.
</script>

<template>
    <div class="min-h-screen bg-slate-50 text-slate-900 dark:bg-slate-950 dark:text-slate-100 transition-colors">
        <main class="mx-auto w-full max-w-7xl px-4 sm:px-6 lg:px-8">
            <slot />
        </main>
    </div>
</template>
