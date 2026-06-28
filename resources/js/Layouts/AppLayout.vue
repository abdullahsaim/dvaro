<script setup>
// Tenant application shell. Wraps all /app/{tenant_slug}/ pages.
// Scaffold shell only — navigation/chrome added per module.
import { computed } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();
const page = usePage();

// Impersonation banner state comes ONLY from the shared 'impersonating' Inertia
// prop, injected by CheckImpersonation middleware — never a separate API call or
// localStorage. Consistent with how 'tenant' / 'auth' are shared.
const impersonating = computed(() => page.props.impersonating ?? null);

function stopImpersonating() {
    router.post('/superadmin/stop-impersonating');
}
</script>

<template>
    <div class="min-h-screen bg-white text-slate-900 dark:bg-slate-950 dark:text-slate-100 transition-colors">
        <!-- Super admin impersonation banner -->
        <div
            v-if="impersonating?.active"
            class="flex items-center justify-center gap-4 bg-amber-500 px-4 py-2 text-sm font-medium text-amber-950"
        >
            <span>{{ t('superadmin.impersonation.banner') }}</span>
            <button
                type="button"
                class="rounded bg-amber-950/10 px-3 py-1 hover:bg-amber-950/20"
                @click="stopImpersonating"
            >
                {{ t('superadmin.impersonation.stop') }}
            </button>
        </div>

        <main class="mx-auto w-full max-w-7xl px-4 sm:px-6 lg:px-8">
            <slot />
        </main>
    </div>
</template>
