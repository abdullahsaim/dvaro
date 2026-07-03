<script setup>
// Customer portal shell. Simpler than the tenant app — a clean top nav (no
// sidebar; customers have few actions) with color-mode toggle + logout.
// Uses the customer-guard auth payload (auth.customer). Deliberately separate
// from AppLayout / SuperAdminLayout / MechanicLayout.
import { computed, onMounted } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { SunIcon, MoonIcon, ArrowRightOnRectangleIcon } from '@heroicons/vue/24/outline';
import Toast from '@/Components/UI/Toast.vue';
import { useColorMode } from '@/composables/useColorMode';

const { t } = useI18n();
const page = usePage();

const slug = computed(() => page.props.tenant?.slug);
const tenantName = computed(() => page.props.tenant?.name);
const customer = computed(() => page.props.auth?.customer ?? null);
const base = computed(() => `/portal/${slug.value}`);
const currentPath = computed(() => (page.url || '').split('?')[0]);

const nav = computed(() => [
    { key: 'dashboard', label: t('customer.portal.dashboard'), href: `${base.value}/dashboard` },
    { key: 'invoices', label: t('customer.portal.invoices'), href: `${base.value}/invoices` },
    { key: 'agreements', label: t('customer.portal.agreements'), href: `${base.value}/agreements` },
    { key: 'profile', label: t('common.profile'), href: `${base.value}/profile` },
]);

function isActive(href) {
    return currentPath.value === href || currentPath.value.startsWith(`${href}/`);
}

function logout() {
    router.post(`${base.value}/logout`);
}

const { isDark, toggle: toggleColorMode, syncFromServer } = useColorMode();
onMounted(syncFromServer);

const iconBtn =
    'inline-flex h-9 w-9 items-center justify-center rounded-control text-ink-500 transition-colors hover:bg-ink-100 hover:text-ink-900 dark:hover:bg-ink-800 dark:hover:text-ink-100';
</script>

<template>
    <div class="min-h-screen bg-ink-50 text-ink-900 transition-colors dark:bg-ink-950 dark:text-ink-100">
        <header class="sticky top-0 z-30 border-b border-ink-200 bg-white/80 backdrop-blur dark:border-ink-800 dark:bg-ink-950/80">
            <div class="mx-auto flex h-16 w-full max-w-5xl items-center gap-6 px-4 sm:px-6 lg:px-8">
                <Link :href="`${base}/dashboard`" class="text-base font-semibold tracking-tight">
                    {{ tenantName }}
                </Link>

                <nav v-if="customer" class="hidden items-center gap-1 sm:flex">
                    <Link
                        v-for="item in nav"
                        :key="item.key"
                        :href="item.href"
                        class="rounded-control px-3 py-1.5 text-sm font-medium transition-colors"
                        :class="isActive(item.href)
                            ? 'bg-ink-100 text-ink-900 dark:bg-ink-800 dark:text-ink-50'
                            : 'text-ink-600 hover:bg-ink-100 hover:text-ink-900 dark:text-ink-400 dark:hover:bg-ink-800 dark:hover:text-ink-100'"
                    >
                        {{ item.label }}
                    </Link>
                </nav>

                <div class="ml-auto flex items-center gap-1">
                    <button type="button" :class="iconBtn" :aria-label="t('common.toggle_theme')" @click="toggleColorMode">
                        <SunIcon v-if="isDark" class="h-5 w-5" />
                        <MoonIcon v-else class="h-5 w-5" />
                    </button>
                    <span v-if="customer" class="ml-1 hidden text-sm text-ink-500 sm:inline">{{ customer.email }}</span>
                    <button
                        v-if="customer"
                        type="button"
                        :class="iconBtn"
                        :aria-label="t('common.logout')"
                        @click="logout"
                    >
                        <ArrowRightOnRectangleIcon class="h-5 w-5" />
                    </button>
                </div>
            </div>

            <!-- Mobile nav row -->
            <nav v-if="customer" class="flex items-center gap-1 border-t border-ink-200 px-4 py-2 sm:hidden dark:border-ink-800">
                <Link
                    v-for="item in nav"
                    :key="item.key"
                    :href="item.href"
                    class="rounded-control px-3 py-1.5 text-sm font-medium transition-colors"
                    :class="isActive(item.href)
                        ? 'bg-ink-100 text-ink-900 dark:bg-ink-800 dark:text-ink-50'
                        : 'text-ink-600 dark:text-ink-400'"
                >
                    {{ item.label }}
                </Link>
            </nav>
        </header>

        <main class="mx-auto w-full max-w-5xl px-4 py-8 sm:px-6 lg:px-8">
            <slot />
        </main>

        <Toast />
    </div>
</template>
