<script setup>
// Monochrome button. Primary = inverted ink (black→white in light, white→black
// in dark). No color accent (pure-monochrome design direction). Focus ring is
// ink with an offset for visibility.
import { computed } from 'vue';

const props = defineProps({
    variant: { type: String, default: 'primary' }, // primary | secondary | ghost | danger
    size: { type: String, default: 'md' }, // sm | md | lg
    type: { type: String, default: 'button' },
    loading: { type: Boolean, default: false },
    disabled: { type: Boolean, default: false },
});

const base =
    'inline-flex items-center justify-center gap-2 font-medium rounded-control transition-colors duration-150 select-none ' +
    'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 ' +
    'focus-visible:ring-ink-950 focus-visible:ring-offset-white ' +
    'dark:focus-visible:ring-ink-100 dark:focus-visible:ring-offset-ink-950 ' +
    'disabled:opacity-50 disabled:pointer-events-none';

const variants = {
    primary:
        'bg-ink-950 text-white hover:bg-ink-800 dark:bg-ink-50 dark:text-ink-950 dark:hover:bg-ink-200',
    secondary:
        'border border-ink-200 bg-white text-ink-900 hover:bg-ink-50 dark:border-ink-800 dark:bg-ink-900 dark:text-ink-100 dark:hover:bg-ink-800',
    ghost: 'text-ink-700 hover:bg-ink-100 dark:text-ink-300 dark:hover:bg-ink-800',
    danger: 'bg-danger-600 text-white hover:bg-danger-700',
};

const sizes = {
    sm: 'h-8 px-3 text-sm',
    md: 'h-10 px-4 text-sm',
    lg: 'h-11 px-5 text-base',
};

const classes = computed(() => [
    base,
    variants[props.variant] ?? variants.primary,
    sizes[props.size] ?? sizes.md,
]);

const isDisabled = computed(() => props.disabled || props.loading);
</script>

<template>
    <button :type="type" :disabled="isDisabled" :class="classes">
        <svg
            v-if="loading"
            class="h-4 w-4 animate-spin"
            viewBox="0 0 24 24"
            fill="none"
            aria-hidden="true"
        >
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z" />
        </svg>
        <slot />
    </button>
</template>
