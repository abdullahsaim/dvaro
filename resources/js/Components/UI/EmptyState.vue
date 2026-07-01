<script setup>
// Empty-list placeholder: icon slot, message, optional action slot. Used when a
// list/table has zero items.
defineProps({
    title: { type: String, default: '' },
    message: { type: String, default: '' },
});
</script>

<template>
    <div class="flex flex-col items-center justify-center px-6 py-14 text-center">
        <div
            v-if="$slots.icon"
            class="mb-4 flex h-12 w-12 items-center justify-center rounded-full bg-ink-100 text-ink-400 dark:bg-ink-800 dark:text-ink-500"
        >
            <slot name="icon" />
        </div>
        <p v-if="title" class="text-sm font-medium text-ink-900 dark:text-ink-100">{{ title }}</p>
        <p v-if="message" class="mt-1 max-w-sm text-sm text-ink-500">{{ message }}</p>
        <div v-if="$slots.action" class="mt-5">
            <slot name="action" />
        </div>
    </div>
</template>
