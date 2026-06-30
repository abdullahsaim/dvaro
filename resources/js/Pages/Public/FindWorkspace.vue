<script setup>
// Global workspace lookup. PUBLIC, pre-tenant. FUNCTIONAL ONLY — design later.
// Posts email to /find-workspace: a single match redirects server-side; multiple
// matches render the picker below; no match shows the register prompt.
import { Head, Link, useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import PublicLayout from '@/Layouts/PublicLayout.vue';

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

        <div class="mx-auto flex min-h-screen max-w-sm flex-col justify-center px-4">
            <h1 class="mb-1 text-2xl font-semibold">{{ t('public.find_workspace.title') }}</h1>
            <p class="mb-6 text-sm text-slate-500 dark:text-slate-400">{{ t('public.find_workspace.subtitle') }}</p>

            <!-- Multiple workspaces: pick one -->
            <div v-if="workspaces.length > 0" class="mb-6">
                <p class="mb-2 text-sm text-slate-600 dark:text-slate-300">{{ t('public.find_workspace.choose') }}</p>
                <ul class="divide-y divide-slate-100 rounded border border-slate-200 dark:divide-slate-800 dark:border-slate-800">
                    <li v-for="ws in workspaces" :key="ws.slug">
                        <Link
                            :href="`/app/${ws.slug}/login`"
                            class="flex items-center justify-between px-4 py-3 text-sm hover:bg-slate-50 dark:hover:bg-slate-800/50"
                        >
                            <span class="font-medium">{{ ws.name }}</span>
                            <span class="text-slate-400">{{ ws.slug }}</span>
                        </Link>
                    </li>
                </ul>
            </div>

            <!-- No workspace found -->
            <p
                v-if="notFound"
                class="mb-4 rounded border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-800 dark:border-amber-900 dark:bg-amber-900/30 dark:text-amber-300"
            >
                {{ t('public.find_workspace.not_found', { email: searchedEmail }) }}
                <Link href="/register" class="font-medium underline">{{ t('public.find_workspace.register_cta') }}</Link>
            </p>

            <form class="space-y-4" @submit.prevent="submit">
                <div>
                    <label for="email" class="block text-sm font-medium">{{ t('auth.email') }}</label>
                    <input
                        id="email"
                        v-model="form.email"
                        type="email"
                        autocomplete="username"
                        required
                        class="mt-1 w-full rounded border border-slate-300 px-3 py-2 dark:border-slate-700 dark:bg-slate-900"
                    />
                    <p v-if="form.errors.email" class="mt-1 text-sm text-red-600">{{ form.errors.email }}</p>
                </div>

                <button
                    type="submit"
                    :disabled="form.processing"
                    class="w-full rounded bg-indigo-600 px-3 py-2 text-white disabled:opacity-50"
                >
                    {{ t('public.find_workspace.submit') }}
                </button>
            </form>
        </div>
    </PublicLayout>
</template>
