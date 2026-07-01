<script setup>
// Loading-skeleton placeholder. Defaults to a single text-line bar; pass custom
// width/height/rounded utility classes via `class` on the element as needed.
defineProps({
    rounded: { type: String, default: 'rounded-control' },
});
</script>

<template>
    <div
        class="animate-pulse bg-ink-200/70 dark:bg-ink-700/60"
        :class="rounded"
        aria-hidden="true"
    />
</template>
