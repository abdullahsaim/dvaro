<script setup>
// Mechanic portal shell. Wraps authenticated /mechanic/{tenant_slug}/ pages.
// FUNCTIONAL ONLY — design pass later. Uses the mechanic-guard auth payload
// shared by ResolveTenantForMechanic (auth.mechanic), never the tenant guard.
import { computed } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();
const page = usePage();

const slug = computed(() => page.props.tenant?.slug);
const mechanic = computed(() => page.props.auth?.mechanic ?? null);
const flash = computed(() => page.props.flash?.success);
const base = computed(() => `/mechanic/${slug.value}`);

function logout() {
    router.post(`${base.value}/logout`);
}
</script>

<template>
    <div class="min-h-screen bg-white text-slate-900 transition-colors dark:bg-slate-950 dark:text-slate-100">
        <header class="border-b border-slate-200 dark:border-slate-800">
            <div class="mx-auto flex w-full max-w-5xl items-center justify-between px-4 py-3 sm:px-6 lg:px-8">
                <Link :href="`${base}/dashboard`" class="text-lg font-semibold">
                    {{ t('workshop.portal_title') }}
                </Link>
                <div v-if="mechanic" class="flex items-center gap-4 text-sm">
                    <span class="text-slate-500 dark:text-slate-400">{{ mechanic.name }}</span>
                    <button type="button" class="text-slate-600 hover:underline dark:text-slate-300" @click="logout">
                        {{ t('auth.logout') }}
                    </button>
                </div>
            </div>
        </header>

        <main class="mx-auto w-full max-w-5xl px-4 py-8 sm:px-6 lg:px-8">
            <p
                v-if="flash"
                class="mb-6 rounded border border-green-200 bg-green-50 px-3 py-2 text-sm text-green-800 dark:border-green-900 dark:bg-green-900/30 dark:text-green-300"
            >
                {{ flash }}
            </p>
            <slot />
        </main>
    </div>
</template>
