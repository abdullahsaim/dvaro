<script setup>
// Text input with label / help / error slots, wired for useForm() error binding.
// v-model compatible. Monochrome styling; error state uses the muted danger token.
import { computed, useId } from 'vue';

const props = defineProps({
    modelValue: { type: [String, Number], default: '' },
    label: { type: String, default: '' },
    type: { type: String, default: 'text' },
    error: { type: String, default: '' },
    help: { type: String, default: '' },
    placeholder: { type: String, default: '' },
    disabled: { type: Boolean, default: false },
    required: { type: Boolean, default: false },
});

defineEmits(['update:modelValue']);

const id = useId();
const describedBy = computed(() => (props.error ? `${id}-error` : props.help ? `${id}-help` : undefined));

const fieldClasses = computed(() => [
    'block w-full rounded-control border bg-white px-3 text-sm text-ink-900 shadow-subtle transition-colors duration-150 h-10',
    'placeholder:text-ink-400 dark:bg-ink-900 dark:text-ink-100 dark:placeholder:text-ink-500',
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
        <input
            :id="id"
            :type="type"
            :value="modelValue"
            :placeholder="placeholder"
            :disabled="disabled"
            :required="required"
            :aria-invalid="error ? 'true' : undefined"
            :aria-describedby="describedBy"
            :class="fieldClasses"
            @input="$emit('update:modelValue', $event.target.value)"
        />
        <p v-if="error" :id="`${id}-error`" class="mt-1.5 text-sm text-danger-600 dark:text-danger-500">
            {{ error }}
        </p>
        <p v-else-if="help" :id="`${id}-help`" class="mt-1.5 text-sm text-ink-500">
            {{ help }}
        </p>
    </div>
</template>
