<script setup>
// Lightweight modal/dialog. Teleported to <body>, backdrop click + Esc close,
// scale-in animation, scroll-locked while open. Used for confirmations (delete,
// etc.). Controlled via the `show` prop; emits `close`.
import { onBeforeUnmount, watch } from 'vue';

const props = defineProps({
    show: { type: Boolean, default: false },
    title: { type: String, default: '' },
    closeOnBackdrop: { type: Boolean, default: true },
});

const emit = defineEmits(['close']);

function close() {
    emit('close');
}

function onKeydown(e) {
    if (e.key === 'Escape' && props.show) close();
}

watch(
    () => props.show,
    (open) => {
        if (typeof document === 'undefined') return;
        document.body.style.overflow = open ? 'hidden' : '';
        if (open) {
            document.addEventListener('keydown', onKeydown);
        } else {
            document.removeEventListener('keydown', onKeydown);
        }
    },
);

onBeforeUnmount(() => {
    document.removeEventListener('keydown', onKeydown);
    document.body.style.overflow = '';
});
</script>

<template>
    <Teleport to="body">
        <Transition
            enter-active-class="transition-opacity duration-200 ease-out"
            enter-from-class="opacity-0"
            leave-active-class="transition-opacity duration-150 ease-in"
            leave-to-class="opacity-0"
        >
            <div
                v-if="show"
                class="fixed inset-0 z-50 flex items-end justify-center p-4 sm:items-center"
                role="dialog"
                aria-modal="true"
            >
                <!-- Backdrop -->
                <div
                    class="absolute inset-0 bg-ink-950/40 backdrop-blur-sm dark:bg-ink-950/60"
                    @click="closeOnBackdrop && close()"
                />

                <!-- Panel -->
                <div
                    class="relative w-full max-w-lg animate-scale-in rounded-card border border-ink-200 bg-white shadow-pop dark:border-ink-800 dark:bg-ink-900"
                >
                    <div v-if="title || $slots.header" class="border-b border-ink-200 px-5 py-4 dark:border-ink-800">
                        <slot name="header">
                            <h2 class="text-base font-semibold text-ink-900 dark:text-ink-50">{{ title }}</h2>
                        </slot>
                    </div>

                    <div class="px-5 py-4 text-sm text-ink-600 dark:text-ink-300">
                        <slot />
                    </div>

                    <div v-if="$slots.footer" class="flex justify-end gap-2 border-t border-ink-200 px-5 py-4 dark:border-ink-800">
                        <slot name="footer" />
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>
