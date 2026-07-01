<script setup>
// Consistent page header: title, optional description, and an actions slot for
// primary buttons (e.g. "Add Vehicle"). Title/description are passed as props
// (already translated by the page) or via slots for richer content.
defineProps({
    title: { type: String, default: '' },
    description: { type: String, default: '' },
});
</script>

<template>
    <div class="flex flex-col gap-4 pb-6 sm:flex-row sm:items-end sm:justify-between">
        <div class="min-w-0">
            <h1 class="truncate text-xl font-semibold tracking-tight text-ink-900 dark:text-ink-50">
                <slot name="title">{{ title }}</slot>
            </h1>
            <p v-if="description || $slots.description" class="mt-1 text-sm text-ink-500">
                <slot name="description">{{ description }}</slot>
            </p>
        </div>
        <div v-if="$slots.actions" class="flex shrink-0 items-center gap-2">
            <slot name="actions" />
        </div>
    </div>
</template>
