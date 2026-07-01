<script setup>
// AI Assistant — conversation list. Design-system pass.
// Start a new Help or Intelligence chat; open or delete existing ones.
// Conversations shown here are the CURRENT user's only (scoped server-side).
import { computed } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import StatusBadge from '@/Components/UI/StatusBadge.vue';

const props = defineProps({
    conversations: { type: Array, required: true },
    modes: { type: Array, required: true },
});

const { t } = useI18n();
const page = usePage();

const base = computed(() => `/app/${page.props.tenant.slug}/ai`);

const modeVariants = {
    help: 'info',
    intelligence: 'neutral',
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

        <PageHeader :title="t('ai.title')" :description="t('ai.subtitle')" />

        <!-- New conversation buttons -->
        <div class="mb-6 grid gap-4 sm:grid-cols-2">
            <button
                type="button"
                class="rounded-card border border-ink-200 bg-white p-4 text-left shadow-subtle transition hover:border-ink-400 hover:shadow-pop dark:border-ink-800 dark:bg-ink-900 dark:hover:border-ink-600"
                @click="startChat('help')"
            >
                <span class="text-sm font-semibold text-ink-900 dark:text-ink-50">{{ t('ai.new_help_chat') }}</span>
                <span class="mt-1 block text-xs text-ink-500">{{ t('ai.mode_help_hint') }}</span>
            </button>
            <button
                type="button"
                class="rounded-card border border-ink-200 bg-white p-4 text-left shadow-subtle transition hover:border-ink-400 hover:shadow-pop dark:border-ink-800 dark:bg-ink-900 dark:hover:border-ink-600"
                @click="startChat('intelligence')"
            >
                <span class="text-sm font-semibold text-ink-900 dark:text-ink-50">{{ t('ai.new_intelligence_chat') }}</span>
                <span class="mt-1 block text-xs text-ink-500">{{ t('ai.mode_intelligence_hint') }}</span>
            </button>
        </div>

        <!-- Conversation list -->
        <div class="overflow-hidden rounded-card border border-ink-200 bg-white shadow-subtle dark:border-ink-800 dark:bg-ink-900">
            <p v-if="conversations.length === 0" class="px-4 py-8 text-center text-sm text-ink-500">
                {{ t('ai.no_conversations') }}
            </p>

            <ul v-else class="divide-y divide-ink-100 dark:divide-ink-800">
                <li
                    v-for="conversation in conversations"
                    :key="conversation.id"
                    class="flex items-center justify-between gap-4 px-4 py-3 hover:bg-ink-50 dark:hover:bg-ink-800/50"
                >
                    <Link :href="`${base}/${conversation.id}`" class="min-w-0 flex-1">
                        <div class="flex items-center gap-2">
                            <StatusBadge :variant="modeVariants[conversation.mode]" :label="t(`ai.mode.${conversation.mode}`)" />
                            <span class="truncate text-sm font-medium text-ink-900 dark:text-ink-50">
                                {{ conversation.title || t('ai.untitled') }}
                            </span>
                        </div>
                        <p class="mt-1 truncate text-xs text-ink-500">{{ preview(conversation) }}</p>
                    </Link>
                    <button
                        type="button"
                        class="shrink-0 rounded px-2 py-1 text-xs text-danger-600 hover:bg-danger-50 dark:text-danger-500 dark:hover:bg-danger-900/20"
                        @click="destroy(conversation.id)"
                    >
                        {{ t('common.delete') }}
                    </button>
                </li>
            </ul>
        </div>
    </AppLayout>
</template>
