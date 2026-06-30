<script setup>
// Super admin panel shell — wraps all /superadmin/ pages. DELIBERATELY separate
// from AppLayout (tenant app): its own chrome, its own nav, its own guard. Never
// share this layout with the tenant/customer/mechanic apps.
// FUNCTIONAL ONLY — design pass later.
import { computed } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();
const page = usePage();

const admin = computed(() => page.props.auth?.superAdmin ?? null);

const nav = [
    { key: 'dashboard', href: '/superadmin/dashboard', label: 'superadmin.nav.dashboard' },
    { key: 'tenants', href: '/superadmin/tenants', label: 'superadmin.nav.tenants' },
    { key: 'plans', href: '/superadmin/plans', label: 'superadmin.nav.plans' },
    { key: 'subscriptions', href: '/superadmin/subscriptions', label: 'superadmin.nav.subscriptions' },
    { key: 'cms', href: '/superadmin/cms', label: 'superadmin.nav.cms' },
    { key: 'demo-requests', href: '/superadmin/demo-requests', label: 'superadmin.nav.demo_requests' },
    { key: 'settings', href: '/superadmin/settings', label: 'superadmin.nav.settings' },
];

const currentPath = computed(() => page.url);

function isActive(href) {
    return currentPath.value.startsWith(href);
}

function logout() {
    router.post('/superadmin/logout');
}
</script>

<template>
    <div class="min-h-screen bg-slate-50 text-slate-900 dark:bg-slate-950 dark:text-slate-100 transition-colors">
        <div class="flex min-h-screen">
            <!-- Sidebar -->
            <aside class="hidden w-60 shrink-0 border-r border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900 md:block">
                <div class="mb-6 px-2">
                    <p class="text-sm font-semibold tracking-tight">{{ t('superadmin.panel') }}</p>
                    <p class="text-xs text-slate-500 dark:text-slate-400">{{ t('app.name') }}</p>
                </div>

                <nav class="space-y-1">
                    <Link
                        v-for="item in nav"
                        :key="item.key"
                        :href="item.href"
                        class="block rounded px-3 py-2 text-sm"
                        :class="isActive(item.href)
                            ? 'bg-slate-900 text-white dark:bg-slate-200 dark:text-slate-900'
                            : 'text-slate-700 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800'"
                    >
                        {{ t(item.label) }}
                    </Link>
                </nav>
            </aside>

            <!-- Main -->
            <div class="flex min-w-0 flex-1 flex-col">
                <header class="flex items-center justify-between border-b border-slate-200 bg-white px-4 py-3 dark:border-slate-800 dark:bg-slate-900">
                    <!-- Mobile nav (simple inline links) -->
                    <nav class="flex flex-wrap gap-2 md:hidden">
                        <Link
                            v-for="item in nav"
                            :key="item.key"
                            :href="item.href"
                            class="rounded px-2 py-1 text-xs"
                            :class="isActive(item.href) ? 'bg-slate-900 text-white dark:bg-slate-200 dark:text-slate-900' : 'text-slate-600 dark:text-slate-300'"
                        >
                            {{ t(item.label) }}
                        </Link>
                    </nav>

                    <div class="ml-auto flex items-center gap-4">
                        <span v-if="admin" class="text-sm text-slate-600 dark:text-slate-300">
                            {{ admin.name }}
                        </span>
                        <button
                            type="button"
                            class="rounded border border-slate-300 px-3 py-1.5 text-sm hover:bg-slate-100 dark:border-slate-700 dark:hover:bg-slate-800"
                            @click="logout"
                        >
                            {{ t('auth.logout') }}
                        </button>
                    </div>
                </header>

                <main class="mx-auto w-full max-w-7xl flex-1 px-4 sm:px-6 lg:px-8">
                    <slot />
                </main>
            </div>
        </div>
    </div>
</template>
