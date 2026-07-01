<script setup>
// Select field matching Input's label/help/error pattern. Options are provided
// via the default slot (<option> elements) for full flexibility. v-model bound.
import { computed, useId } from 'vue';

const props = defineProps({
    modelValue: { type: [String, Number, Boolean], default: '' },
    label: { type: String, default: '' },
    error: { type: String, default: '' },
    help: { type: String, default: '' },
    disabled: { type: Boolean, default: false },
    required: { type: Boolean, default: false },
});

defineEmits(['update:modelValue']);

const id = useId();
const describedBy = computed(() => (props.error ? `${id}-error` : props.help ? `${id}-help` : undefined));

const fieldClasses = computed(() => [
    'block w-full appearance-none rounded-control border bg-white px-3 pr-9 text-sm text-ink-900 shadow-subtle transition-colors duration-150 h-10',
    'dark:bg-ink-900 dark:text-ink-100',
    'focus:outline-none focus:ring-2 focus:ring-offset-0 disabled:opacity-50 disabled:pointer-events-none',
    props.error
        ? 'border-danger-500 focus:border-danger-500 focus:ring-danger-500/40'
        : 'border-ink-200 focus:border-ink-400 focus:ring-ink-900/15 dark:border-ink-800 dark:focus:border-ink-600 dark:focus:ring-ink-100/15',
]);
</script>

<template>
    <div>
        <label v-if="label" :for="id" class="mb-1.5 block text-sm font-medium text-ink-700 dark:text-ink-300">
            {{ label }}
            <span v-if="required" class="text-danger-500">*</span>
        </label>
        <div class="relative">
            <select
                :id="id"
                :value="modelValue"
                :disabled="disabled"
                :required="required"
                :aria-invalid="error ? 'true' : undefined"
                :aria-describedby="describedBy"
                :class="fieldClasses"
                @change="$emit('update:modelValue', $event.target.value)"
            >
                <slot />
            </select>
            <svg
                class="pointer-events-none absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 text-ink-400"
                viewBox="0 0 20 20"
                fill="currentColor"
                aria-hidden="true"
            >
                <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.17l3.71-3.94a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd" />
            </svg>
        </div>
        <p v-if="error" :id="`${id}-error`" class="mt-1.5 text-sm text-danger-600 dark:text-danger-500">
            {{ error }}
        </p>
        <p v-else-if="help" :id="`${id}-help`" class="mt-1.5 text-sm text-ink-500">
            {{ help }}
        </p>
    </div>
</template>
