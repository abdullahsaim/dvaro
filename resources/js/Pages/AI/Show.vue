<script setup>
// AI Assistant — chat thread. Design-system pass.
// Async chat: posts to /ai/chat via axios (NOT Inertia) and appends the reply
// without a page reload. Handles loading, error and rate-limit states, and
// auto-scrolls to the latest message.
import { computed, nextTick, onMounted, ref } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import AppLayout from '@/Layouts/AppLayout.vue';
import StatusBadge from '@/Components/UI/StatusBadge.vue';
import Button from '@/Components/UI/Button.vue';
import Input from '@/Components/UI/Input.vue';

const props = defineProps({
    conversation: { type: Object, required: true }, // { id, mode, title }
    messages: { type: Array, required: true },
});

const { t } = useI18n();
const page = usePage();

const base = computed(() => `/app/${page.props.tenant.slug}/ai`);

// Local thread: seeded from server props, grown client-side as we chat.
const thread = ref([...props.messages]);
const input = ref('');
const loading = ref(false);
const error = ref('');
const threadEl = ref(null);

let tempId = -1;

const modeVariants = {
    help: 'info',
    intelligence: 'neutral',
};

function scrollToBottom() {
    nextTick(() => {
        if (threadEl.value) {
            threadEl.value.scrollTop = threadEl.value.scrollHeight;
        }
    });
}

onMounted(scrollToBottom);

async function send() {
    const text = input.value.trim();
    if (!text || loading.value) {
        return;
    }

    // Optimistically show the user's message.
    const optimistic = { id: tempId--, role: 'user', content: text };
    thread.value.push(optimistic);
    input.value = '';
    loading.value = true;
    error.value = '';
    scrollToBottom();

    try {
        const { data } = await window.axios.post(`${base.value}/chat`, {
            conversation_id: props.conversation.id,
            mode: props.conversation.mode,
            message: text,
        });
        thread.value.push(data.message);
    } catch (e) {
        // The message was not processed — drop the optimistic bubble and restore
        // the input so the user can retry.
        thread.value = thread.value.filter((m) => m !== optimistic);
        input.value = text;
        error.value = e?.response?.status === 429 ? t('ai.rate_limited') : t('ai.error');
    } finally {
        loading.value = false;
        scrollToBottom();
    }
}
</script>

<template>
    <AppLayout>
        <Head :title="conversation.title || t('ai.title')" />

        <div class="flex h-[calc(100vh-8rem)] flex-col">
            <!-- Header -->
            <div class="mb-4 flex items-center gap-3">
                <Link :href="base" class="text-sm font-medium text-ink-500 hover:underline">
                    ← {{ t('ai.title') }}
                </Link>
                <StatusBadge :variant="modeVariants[conversation.mode]" :label="t(`ai.mode.${conversation.mode}`)" />
                <h1 class="truncate text-lg font-semibold text-ink-900 dark:text-ink-50">
                    {{ conversation.title || t('ai.untitled') }}
                </h1>
            </div>

            <!-- Messages -->
            <div
                ref="threadEl"
                class="flex-1 space-y-4 overflow-y-auto rounded-card border border-ink-200 bg-ink-50 p-4 dark:border-ink-800 dark:bg-ink-900/50"
            >
                <p v-if="thread.length === 0" class="py-8 text-center text-sm text-ink-500">
                    {{ t('ai.empty_thread') }}
                </p>

                <div
                    v-for="message in thread"
                    :key="message.id"
                    class="flex"
                    :class="message.role === 'user' ? 'justify-end' : 'justify-start'"
                >
                    <div
                        class="max-w-[80%] whitespace-pre-wrap rounded-2xl px-4 py-2 text-sm"
                        :class="message.role === 'user'
                            ? 'bg-ink-950 text-white dark:bg-ink-50 dark:text-ink-950'
                            : 'bg-white text-ink-900 ring-1 ring-ink-200 dark:bg-ink-800 dark:text-ink-100 dark:ring-ink-700'"
                    >
                        <span class="mb-0.5 block text-[10px] font-medium uppercase tracking-wide opacity-60">
                            {{ message.role === 'user' ? t('ai.you') : t('ai.assistant') }}
                        </span>
                        {{ message.content }}
                    </div>
                </div>

                <!-- Loading indicator -->
                <div v-if="loading" class="flex justify-start">
                    <div class="rounded-2xl bg-white px-4 py-2 text-sm text-ink-500 ring-1 ring-ink-200 dark:bg-ink-800 dark:text-ink-400 dark:ring-ink-700">
                        {{ t('ai.thinking') }}
                    </div>
                </div>
            </div>

            <!-- Error -->
            <p v-if="error" class="mt-2 text-sm text-danger-600 dark:text-danger-500">{{ error }}</p>

            <!-- Composer -->
            <form class="mt-3 flex items-end gap-2" @submit.prevent="send">
                <Input
                    v-model="input"
                    :placeholder="t('ai.placeholder')"
                    :disabled="loading"
                    class="flex-1"
                />
                <Button type="submit" :loading="loading" :disabled="!input.trim()">{{ t('ai.send') }}</Button>
            </form>
        </div>
    </AppLayout>
</template>
