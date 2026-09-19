<script setup>
// Shell for the tenant's PUBLIC lead form pages (form / thank-you / unavailable).
//
//  - standalone (share link / QR): centred card on the page, tenant name as the
//    heading, a quiet "Powered by DVARO" footer.
//  - embedded (iframe on the tenant's website): no chrome, transparent
//    background so it sits on the host page, and the document height is posted
//    to the parent (the embed snippet resizes the iframe — origin-checked there).
import { onBeforeUnmount, onMounted, ref } from 'vue';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    embedded: { type: Boolean, default: false },
    tenantName: { type: String, default: '' },
});

const { t } = useI18n();

let observer = null;
const content = ref(null);

// Measure the CONTENT, not documentElement.scrollHeight — the latter is never
// smaller than the iframe's own height, so the frame could grow but never
// shrink back (e.g. after validation errors clear).
function postHeight() {
    if (!content.value) return;
    window.parent?.postMessage(
        { type: 'dvaro-lead-form:height', height: Math.ceil(content.value.getBoundingClientRect().height) },
        '*',
    );
}

onMounted(() => {
    if (!props.embedded) return;

    document.documentElement.style.background = 'transparent';
    document.body.style.background = 'transparent';

    postHeight();
    observer = new ResizeObserver(postHeight);
    observer.observe(content.value);
});

onBeforeUnmount(() => observer?.disconnect());
</script>

<template>
    <div v-if="embedded" ref="content" class="px-1 py-2">
        <slot />
    </div>

    <div v-else class="min-h-screen bg-ink-50 px-4 py-10 sm:py-16 dark:bg-ink-950">
        <main class="mx-auto w-full max-w-xl">
            <p v-if="tenantName" class="text-center text-sm font-medium uppercase tracking-wide text-ink-500">
                {{ tenantName }}
            </p>
            <div class="mt-4 rounded-card border border-ink-200 bg-white p-6 shadow-subtle sm:p-8 dark:border-ink-800 dark:bg-ink-900">
                <slot />
            </div>
            <p class="mt-6 text-center text-xs text-ink-400">{{ t('crm.public_form.powered_by') }}</p>
        </main>
    </div>
</template>
