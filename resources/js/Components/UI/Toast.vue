<script setup>
// Toast stack. Mounted once per layout. Renders the shared useToast() store AND
// auto-ingests Inertia flash messages (flash.success / flash.error) so any
// controller redirect()->with('success', ...) surfaces as a toast with no
// per-page wiring. Auto-dismiss after 4s (handled in the store).
import { watch } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { XMarkIcon, CheckCircleIcon, ExclamationCircleIcon, InformationCircleIcon } from '@heroicons/vue/24/outline';
import { useToast } from '@/composables/useToast';

const page = usePage();
const { toasts, remove, success, error } = useToast();

// Each Inertia visit replaces the flash object; push whatever it carries.
watch(
    () => page.props.flash,
    (flash) => {
        if (!flash) return;
        if (flash.success) success(flash.success);
        if (flash.error) error(flash.error);
    },
    { immediate: true, deep: true },
);

const icons = {
    success: CheckCircleIcon,
    error: ExclamationCircleIcon,
    info: InformationCircleIcon,
};

const accent = {
    success: 'text-success-600 dark:text-success-500',
    error: 'text-danger-600 dark:text-danger-500',
    info: 'text-info-600 dark:text-info-500',
};
</script>

<template>
    <Teleport to="body">
        <div class="pointer-events-none fixed inset-x-0 bottom-0 z-[60] flex flex-col items-center gap-2 p-4 sm:items-end">
            <TransitionGroup
                enter-active-class="transition duration-200 ease-out"
                enter-from-class="translate-y-2 opacity-0 sm:translate-y-0 sm:translate-x-2"
                leave-active-class="transition duration-150 ease-in"
                leave-to-class="opacity-0"
            >
                <div
                    v-for="toast in toasts"
                    :key="toast.id"
                    class="pointer-events-auto flex w-full max-w-sm items-start gap-3 rounded-card border border-ink-200 bg-white px-4 py-3 shadow-pop dark:border-ink-800 dark:bg-ink-900"
                    role="status"
                >
                    <component :is="icons[toast.type] ?? icons.info" class="mt-0.5 h-5 w-5 shrink-0" :class="accent[toast.type] ?? accent.info" />
                    <p class="min-w-0 flex-1 text-sm text-ink-800 dark:text-ink-100">{{ toast.message }}</p>
                    <button
                        type="button"
                        class="-mr-1 -mt-0.5 rounded p-1 text-ink-400 transition-colors hover:bg-ink-100 hover:text-ink-700 dark:hover:bg-ink-800 dark:hover:text-ink-200"
                        @click="remove(toast.id)"
                    >
                        <XMarkIcon class="h-4 w-4" />
                    </button>
                </div>
            </TransitionGroup>
        </div>
    </Teleport>
</template>
