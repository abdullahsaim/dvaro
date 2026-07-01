<script setup>
// Tenant application shell: collapsible sidebar + top bar + content area.
// Wraps all /app/{tenant_slug}/ pages. Mobile sidebar becomes a slide-over
// drawer (handled inside Sidebar). Impersonation banner + flash toasts live
// here so every tenant page gets them for free.
import { computed, onMounted } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import {
    HomeIcon,
    TruckIcon,
    UsersIcon,
    DocumentTextIcon,
    BanknotesIcon,
    UserPlusIcon,
    WrenchScrewdriverIcon,
    ChartBarIcon,
    SparklesIcon,
    IdentificationIcon,
    Cog6ToothIcon,
} from '@heroicons/vue/24/outline';
import Sidebar from '@/Components/UI/Sidebar.vue';
import TopBar from '@/Components/UI/TopBar.vue';
import Toast from '@/Components/UI/Toast.vue';
import { useColorMode } from '@/composables/useColorMode';

const { t } = useI18n();
const page = usePage();

const tenant = computed(() => page.props.tenant ?? null);
const tenantName = computed(() => tenant.value?.name ?? '');
const slug = computed(() => tenant.value?.slug ?? '');
const user = computed(() => page.props.auth?.user ?? null);

const logoutHref = computed(() => (slug.value ? `/app/${slug.value}/logout` : ''));

// Current path (query stripped) for active-link matching.
const currentPath = computed(() => (page.url || '').split('?')[0]);

function isActive(href, exact = false) {
    const p = currentPath.value;
    return exact ? p === href : p === href || p.startsWith(`${href}/`);
}

const navItems = computed(() => {
    const base = `/app/${slug.value}`;
    return [
        { key: 'dashboard', label: t('nav.dashboard'), href: `${base}/dashboard`, icon: HomeIcon, exact: true },
        { key: 'fleet', label: t('nav.fleet'), href: `${base}/fleet`, icon: TruckIcon },
        { key: 'customers', label: t('nav.customers'), href: `${base}/customers`, icon: UsersIcon },
        { key: 'agreements', label: t('nav.agreements'), href: `${base}/agreements`, icon: DocumentTextIcon },
        { key: 'invoices', label: t('nav.invoices'), href: `${base}/invoices`, icon: BanknotesIcon },
        { key: 'leads', label: t('nav.leads'), href: `${base}/leads`, icon: UserPlusIcon },
        { key: 'workshop', label: t('nav.workshop'), href: `${base}/workshop`, icon: WrenchScrewdriverIcon },
        { key: 'reports', label: t('nav.reports'), href: `${base}/reports`, icon: ChartBarIcon },
        { key: 'ai', label: t('nav.ai'), href: `${base}/ai`, icon: SparklesIcon },
        { key: 'mechanics', label: t('nav.mechanics'), href: `${base}/mechanics`, icon: IdentificationIcon },
        { key: 'settings', label: t('nav.settings'), href: `${base}/notifications/settings`, icon: Cog6ToothIcon },
    ].map((item) => ({ ...item, active: isActive(item.href, item.exact) }));
});

// Impersonation banner (shared prop from CheckImpersonation middleware).
const impersonating = computed(() => page.props.impersonating ?? null);
function stopImpersonating() {
    router.post('/superadmin/stop-impersonating');
}

// Adopt the server-saved color preference on a fresh browser.
const { syncFromServer } = useColorMode();
onMounted(syncFromServer);
</script>

<template>
    <div class="flex min-h-screen flex-col bg-ink-50 text-ink-900 dark:bg-ink-950 dark:text-ink-100">
        <!-- Impersonation banner -->
        <div
            v-if="impersonating?.active"
            class="flex items-center justify-center gap-4 bg-warning-500 px-4 py-2 text-sm font-medium text-white"
        >
            <span>{{ t('superadmin.impersonation.banner') }}</span>
            <button
                type="button"
                class="rounded-control bg-white/20 px-3 py-1 transition-colors hover:bg-white/30"
                @click="stopImpersonating"
            >
                {{ t('superadmin.impersonation.stop') }}
            </button>
        </div>

        <div class="flex min-h-0 flex-1">
            <Sidebar :items="navItems" title="DVARO" :context-label="tenantName" />

            <div class="flex min-w-0 flex-1 flex-col">
                <TopBar :title="tenantName" :user="user" :logout-href="logoutHref" />

                <main class="flex-1 overflow-y-auto">
                    <div class="mx-auto w-full max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
                        <slot />
                    </div>
                </main>
            </div>
        </div>

        <Toast />
    </div>
</template>
