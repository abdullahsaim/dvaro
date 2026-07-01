<script setup>
// Global workspace lookup. PUBLIC, pre-tenant. Design-system pass.
// Posts email to /find-workspace: a single match redirects server-side; multiple
// matches render the picker below; no match shows the register prompt.
import { Head, Link, useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import Button from '@/Components/UI/Button.vue';
import Input from '@/Components/UI/Input.vue';

const props = defineProps({
    searchedEmail: { type: String, default: null },
    notFound: { type: Boolean, default: false },
    workspaces: { type: Array, default: () => [] },
});

const { t } = useI18n();

const form = useForm({ email: props.searchedEmail ?? '' });

function submit() {
    form.post('/find-workspace');
}
</script>

<template>
    <PublicLayout>
        <Head :title="t('public.find_workspace.title')" />

        <div class="mx-auto flex min-h-[70vh] max-w-sm flex-col justify-center px-4 py-16">
            <div class="rounded-card border border-ink-200 bg-white p-6 shadow-subtle dark:border-ink-800 dark:bg-ink-900">
                <h1 class="mb-1 text-2xl font-semibold text-ink-900 dark:text-ink-50">{{ t('public.find_workspace.title') }}</h1>
                <p class="mb-6 text-sm text-ink-500">{{ t('public.find_workspace.subtitle') }}</p>

                <!-- Multiple workspaces: pick one -->
                <div v-if="workspaces.length > 0" class="mb-6">
                    <p class="mb-2 text-sm text-ink-600 dark:text-ink-300">{{ t('public.find_workspace.choose') }}</p>
                    <ul class="divide-y divide-ink-100 overflow-hidden rounded-card border border-ink-200 dark:divide-ink-800 dark:border-ink-800">
                        <li v-for="ws in workspaces" :key="ws.slug">
                            <Link
                                :href="`/app/${ws.slug}/login`"
                                class="flex items-center justify-between px-4 py-3 text-sm hover:bg-ink-50 dark:hover:bg-ink-800/50"
                            >
                                <span class="font-medium text-ink-900 dark:text-ink-50">{{ ws.name }}</span>
                                <span class="text-ink-400">{{ ws.slug }}</span>
                            </Link>
                        </li>
                    </ul>
                </div>

                <!-- No workspace found -->
                <p
                    v-if="notFound"
                    class="mb-4 rounded-control border border-warning-200 bg-warning-50 px-3 py-2 text-sm text-warning-800 dark:border-warning-900 dark:bg-warning-900/20 dark:text-warning-300"
                >
                    {{ t('public.find_workspace.not_found', { email: searchedEmail }) }}
                    <Link href="/register" class="font-medium underline">{{ t('public.find_workspace.register_cta') }}</Link>
                </p>

                <form class="space-y-4" @submit.prevent="submit">
                    <Input v-model="form.email" type="email" autocomplete="username" required :label="t('auth.email')" :error="form.errors.email" />
                    <Button type="submit" class="w-full" :loading="form.processing">{{ t('public.find_workspace.submit') }}</Button>
                </form>
            </div>
        </div>
    </PublicLayout>
</template>
