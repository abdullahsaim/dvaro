<script setup>
// AI Assistant — chat thread. FUNCTIONAL ONLY — design pass later.
// Async chat: posts to /ai/chat via axios (NOT Inertia) and appends the reply
// without a page reload. Handles loading, error and rate-limit states, and
// auto-scrolls to the latest message.
import { computed, nextTick, onMounted, ref } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import AppLayout from '@/Layouts/AppLayout.vue';

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

const modeColors = {
    help: 'bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-300',
    intelligence: 'bg-purple-100 text-purple-800 dark:bg-purple-900/40 dark:text-purple-300',
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

        <div class="flex h-[calc(100vh-8rem)] flex-col py-6">
            <!-- Header -->
            <div class="mb-4 flex items-center gap-3">
                <Link :href="base" class="text-sm text-slate-500 hover:underline dark:text-slate-400">
                    ← {{ t('ai.title') }}
                </Link>
                <span
                    class="rounded-full px-2 py-0.5 text-xs font-medium"
                    :class="modeColors[conversation.mode]"
                >
                    {{ t(`ai.mode.${conversation.mode}`) }}
                </span>
                <h1 class="truncate text-lg font-semibold">
                    {{ conversation.title || t('ai.untitled') }}
                </h1>
            </div>

            <!-- Messages -->
            <div
                ref="threadEl"
                class="flex-1 space-y-4 overflow-y-auto rounded-xl border border-slate-200 bg-slate-50 p-4 dark:border-slate-800 dark:bg-slate-900/50"
            >
                <p
                    v-if="thread.length === 0"
                    class="py-8 text-center text-sm text-slate-500 dark:text-slate-400"
                >
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
                            ? 'bg-blue-600 text-white'
                            : 'bg-white text-slate-900 ring-1 ring-slate-200 dark:bg-slate-800 dark:text-slate-100 dark:ring-slate-700'"
                    >
                        <span class="mb-0.5 block text-[10px] font-medium uppercase tracking-wide opacity-60">
                            {{ message.role === 'user' ? t('ai.you') : t('ai.assistant') }}
                        </span>
                        {{ message.content }}
                    </div>
                </div>

                <!-- Loading indicator -->
                <div v-if="loading" class="flex justify-start">
                    <div class="rounded-2xl bg-white px-4 py-2 text-sm text-slate-500 ring-1 ring-slate-200 dark:bg-slate-800 dark:text-slate-400 dark:ring-slate-700">
                        {{ t('ai.thinking') }}
                    </div>
                </div>
            </div>

            <!-- Error -->
            <p v-if="error" class="mt-2 text-sm text-red-600 dark:text-red-400">{{ error }}</p>

            <!-- Composer -->
            <form class="mt-3 flex gap-2" @submit.prevent="send">
                <input
                    v-model="input"
                    type="text"
                    :placeholder="t('ai.placeholder')"
                    :disabled="loading"
                    class="flex-1 rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500 disabled:opacity-60 dark:border-slate-700 dark:bg-slate-900"
                />
                <button
                    type="submit"
                    :disabled="loading || !input.trim()"
                    class="rounded-xl bg-blue-600 px-5 py-2 text-sm font-medium text-white transition hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-50"
                >
                    {{ t('ai.send') }}
                </button>
            </form>
        </div>
    </AppLayout>
</template>
