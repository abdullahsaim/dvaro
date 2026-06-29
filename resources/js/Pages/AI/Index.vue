<script setup>
// AI Assistant — conversation list. FUNCTIONAL ONLY — design pass later.
// Start a new Help or Intelligence chat; open or delete existing ones.
// Conversations shown here are the CURRENT user's only (scoped server-side).
import { computed } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    conversations: { type: Array, required: true },
    modes: { type: Array, required: true },
});

const { t } = useI18n();
const page = usePage();

const base = computed(() => `/app/${page.props.tenant.slug}/ai`);
const flash = computed(() => page.props.flash?.success);

const modeColors = {
    help: 'bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-300',
    intelligence: 'bg-purple-100 text-purple-800 dark:bg-purple-900/40 dark:text-purple-300',
};

function startChat(mode) {
    router.post(`${base.value}/new`, { mode });
}

function destroy(id) {
    if (window.confirm(t('ai.delete_confirm'))) {
        router.delete(`${base.value}/${id}`, { preserveScroll: true });
    }
}

function preview(conversation) {
    const msg = conversation.latest_message;
    return msg ? msg.content : t('ai.empty_thread');
}
</script>

<template>
    <AppLayout>
        <Head :title="t('ai.title')" />

        <div class="py-8">
            <div class="mb-6">
                <h1 class="text-2xl font-semibold">{{ t('ai.title') }}</h1>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ t('ai.subtitle') }}</p>
            </div>

            <div
                v-if="flash"
                class="mb-4 rounded-lg bg-green-50 px-4 py-3 text-sm text-green-800 dark:bg-green-900/30 dark:text-green-300"
            >
                {{ flash }}
            </div>

            <!-- New conversation buttons -->
            <div class="mb-6 grid gap-4 sm:grid-cols-2">
                <button
                    type="button"
                    class="rounded-xl border border-slate-200 bg-white p-4 text-left transition hover:border-blue-400 hover:shadow dark:border-slate-800 dark:bg-slate-900"
                    @click="startChat('help')"
                >
                    <span class="text-sm font-semibold text-blue-700 dark:text-blue-300">{{ t('ai.new_help_chat') }}</span>
                    <span class="mt-1 block text-xs text-slate-500 dark:text-slate-400">{{ t('ai.mode_help_hint') }}</span>
                </button>
                <button
                    type="button"
                    class="rounded-xl border border-slate-200 bg-white p-4 text-left transition hover:border-purple-400 hover:shadow dark:border-slate-800 dark:bg-slate-900"
                    @click="startChat('intelligence')"
                >
                    <span class="text-sm font-semibold text-purple-700 dark:text-purple-300">{{ t('ai.new_intelligence_chat') }}</span>
                    <span class="mt-1 block text-xs text-slate-500 dark:text-slate-400">{{ t('ai.mode_intelligence_hint') }}</span>
                </button>
            </div>

            <!-- Conversation list -->
            <div class="overflow-hidden rounded-xl border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
                <p
                    v-if="conversations.length === 0"
                    class="px-4 py-8 text-center text-sm text-slate-500 dark:text-slate-400"
                >
                    {{ t('ai.no_conversations') }}
                </p>

                <ul v-else class="divide-y divide-slate-100 dark:divide-slate-800">
                    <li
                        v-for="conversation in conversations"
                        :key="conversation.id"
                        class="flex items-center justify-between gap-4 px-4 py-3 hover:bg-slate-50 dark:hover:bg-slate-800/50"
                    >
                        <Link :href="`${base}/${conversation.id}`" class="min-w-0 flex-1">
                            <div class="flex items-center gap-2">
                                <span
                                    class="rounded-full px-2 py-0.5 text-xs font-medium"
                                    :class="modeColors[conversation.mode]"
                                >
                                    {{ t(`ai.mode.${conversation.mode}`) }}
                                </span>
                                <span class="truncate text-sm font-medium">
                                    {{ conversation.title || t('ai.untitled') }}
                                </span>
                            </div>
                            <p class="mt-1 truncate text-xs text-slate-500 dark:text-slate-400">
                                {{ preview(conversation) }}
                            </p>
                        </Link>
                        <button
                            type="button"
                            class="shrink-0 rounded px-2 py-1 text-xs text-red-600 hover:bg-red-50 dark:hover:bg-red-900/30"
                            @click="destroy(conversation.id)"
                        >
                            {{ t('common.delete') }}
                        </button>
                    </li>
                </ul>
            </div>
        </div>
    </AppLayout>
</template>
