<script setup>
// Dashboard KPI card: label, value, optional trend indicator and icon slot.
// Trend direction colors use the muted status tokens (up=success, down=danger);
// `trendUp` flips the semantics for metrics where down is good (e.g. overdue).
import { computed } from 'vue';

const props = defineProps({
    label: { type: String, required: true },
    value: { type: [String, Number], default: '' },
    trend: { type: String, default: '' }, // e.g. "+12%" — optional
    trendDirection: { type: String, default: '' }, // 'up' | 'down' | ''
});

const trendClasses = computed(() => {
    if (props.trendDirection === 'up') return 'text-success-600 dark:text-success-500';
    if (props.trendDirection === 'down') return 'text-danger-600 dark:text-danger-500';
    return 'text-ink-500';
});
</script>

<template>
    <div class="rounded-card border border-ink-200 bg-white p-5 shadow-subtle transition-colors dark:border-ink-800 dark:bg-ink-900">
        <div class="flex items-start justify-between gap-3">
            <p class="text-sm font-medium text-ink-500">{{ label }}</p>
            <span v-if="$slots.icon" class="text-ink-400">
                <slot name="icon" />
            </span>
        </div>
        <div class="mt-2 flex items-baseline gap-2">
            <p class="text-2xl font-semibold tracking-tight text-ink-900 dark:text-ink-50">
                <slot>{{ value }}</slot>
            </p>
            <span v-if="trend" class="text-sm font-medium" :class="trendClasses">{{ trend }}</span>
        </div>
        <p v-if="$slots.description" class="mt-1 text-sm text-ink-500">
            <slot name="description" />
        </p>
    </div>
</template>
