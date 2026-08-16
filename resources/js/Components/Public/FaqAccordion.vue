<script setup>
// Animated FAQ accordion for the public pages. One item open at a time; the
// expand/collapse animates via the CSS grid 0fr→1fr trick (height animation
// with no JS measuring, no layout jank). Content comes from the CMS.
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';

defineProps({
    // [{ question, answer }]
    items: { type: Array, default: () => [] },
});

const { t } = useI18n();

const openIndex = ref(0);

function toggle(index) {
    openIndex.value = openIndex.value === index ? null : index;
}
</script>

<template>
    <div class="divide-y divide-ink-200 rounded-card border border-ink-200 bg-white shadow-subtle dark:divide-ink-800 dark:border-ink-800 dark:bg-ink-900">
        <div v-for="(item, index) in items" :key="index">
            <button
                type="button"
                class="flex w-full items-center justify-between gap-4 px-5 py-4 text-left transition-colors hover:bg-ink-50 dark:hover:bg-ink-800/50 sm:px-6"
                :aria-expanded="openIndex === index"
                :aria-label="t('public.faq.toggle_label', { question: item.question })"
                @click="toggle(index)"
            >
                <span class="text-sm font-semibold text-ink-900 dark:text-ink-50 sm:text-base">{{ item.question }}</span>
                <svg
                    class="h-5 w-5 shrink-0 text-ink-400 transition-transform duration-300 ease-smooth"
                    :class="openIndex === index ? 'rotate-45' : ''"
                    fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"
                >
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 5v14m-7-7h14" />
                </svg>
            </button>
            <div
                class="grid transition-[grid-template-rows] duration-300 ease-smooth motion-reduce:transition-none"
                :style="{ gridTemplateRows: openIndex === index ? '1fr' : '0fr' }"
            >
                <div class="overflow-hidden">
                    <p class="px-5 pb-5 text-sm leading-relaxed text-ink-600 dark:text-ink-300 sm:px-6">{{ item.answer }}</p>
                </div>
            </div>
        </div>
    </div>
</template>
