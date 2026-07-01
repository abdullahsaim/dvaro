<script setup>
// Generic status pill driven by a semantic `variant`. Used across vehicle,
// invoice, agreement, lead and maintenance statuses — the page maps its own
// status enum to a variant and passes a translated label.
//
// The legacy fleet-specific Components/StatusBadge.vue has been removed; every
// module page maps its own status enum to a variant and uses this generic badge.
import { computed } from 'vue';

const props = defineProps({
    variant: { type: String, default: 'neutral' }, // success | warning | danger | info | neutral
    label: { type: String, default: '' },
});

const variants = {
    success:
        'bg-success-50 text-success-700 ring-success-100 dark:bg-success-900/40 dark:text-success-100 dark:ring-success-900',
    warning:
        'bg-warning-50 text-warning-700 ring-warning-100 dark:bg-warning-900/40 dark:text-warning-100 dark:ring-warning-900',
    danger:
        'bg-danger-50 text-danger-700 ring-danger-100 dark:bg-danger-900/40 dark:text-danger-100 dark:ring-danger-900',
    info: 'bg-info-50 text-info-700 ring-info-100 dark:bg-info-900/40 dark:text-info-100 dark:ring-info-900',
    neutral:
        'bg-ink-100 text-ink-700 ring-ink-200 dark:bg-ink-800 dark:text-ink-300 dark:ring-ink-700',
};

const classes = computed(() => variants[props.variant] ?? variants.neutral);
</script>

<template>
    <span
        class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ring-1 ring-inset"
        :class="classes"
    >
        <slot>{{ label }}</slot>
    </span>
</template>
