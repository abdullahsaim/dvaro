<script setup>
// Public landing website shell. Wraps all CMS-controlled marketing pages plus
// the public auth pages (login/register). Top nav + footer + responsive mobile
// menu + color-mode toggle. Monochrome design tokens (ink), pure-monochrome.
//
// NOTE on auth links: tenant login is path-based (/app/{slug}/login). "Log in"
// routes to the global workspace-resolver (/find-workspace), which looks the
// tenant up by email and forwards to that tenant's login. "Get started" routes
// to /register (the single global signup entry).
//
// Color mode here is localStorage-only on guest pages (no server prop); the
// composable handles that gracefully.
import { computed, ref } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { SunIcon, MoonIcon, Bars3Icon, XMarkIcon } from '@heroicons/vue/24/outline';
import { useColorMode } from '@/composables/useColorMode';
import { cmsImage } from '@/cms/defaultImages.js';

const { t } = useI18n();
const page = usePage();

// CMS-managed theme logos (shared 'branding' prop). Each mode prefers its own
// logo, falls back to the other, then to the bundled defaults via cmsImage —
// so the header never renders empty. Theme switching is pure CSS (dark:hidden /
// hidden dark:block), no re-render needed on toggle.
const branding = computed(() => page.props.branding ?? {});
const logoLight = computed(
    () => cmsImage('logo_light', branding.value.logo_light) || cmsImage('logo_dark', branding.value.logo_dark),
);
const logoDark = computed(
    () => cmsImage('logo_dark', branding.value.logo_dark) || cmsImage('logo_light', branding.value.logo_light),
);

const mobileOpen = ref(false);

const navLinks = [
    { href: '/pricing', label: 'public.nav.pricing' },
    { href: '/about', label: 'public.nav.about' },
    { href: '/contact', label: 'public.nav.contact' },
];

const registerHref = '/register';
const loginHref = '/find-workspace';

const year = new Date().getFullYear();

const { isDark, toggle: toggleColorMode } = useColorMode();

const iconBtn =
    'inline-flex h-9 w-9 items-center justify-center rounded-control text-ink-500 transition-colors hover:bg-ink-100 hover:text-ink-900 dark:hover:bg-ink-800 dark:hover:text-ink-100';
</script>

<template>
    <div class="flex min-h-screen flex-col bg-white text-ink-900 transition-colors dark:bg-ink-950 dark:text-ink-100">
        <!-- Header -->
        <header class="sticky top-0 z-30 border-b border-ink-200/70 bg-white/80 backdrop-blur dark:border-ink-800/70 dark:bg-ink-950/80">
            <div class="mx-auto flex h-16 w-full max-w-7xl items-center justify-between px-4 sm:px-6 lg:px-8">
                <Link href="/" class="flex items-center">
                    <template v-if="logoLight || logoDark">
                        <img :src="logoLight" :alt="t('app.name')" class="h-7 w-auto max-w-40 object-contain object-left dark:hidden sm:h-8" />
                        <img :src="logoDark" :alt="t('app.name')" class="hidden h-7 w-auto max-w-40 object-contain object-left dark:block sm:h-8" />
                    </template>
                    <template v-else>
                        <span class="grid h-8 w-8 place-items-center rounded-control bg-ink-950 text-sm font-bold text-white dark:bg-ink-50 dark:text-ink-950">D</span>
                        <span class="ml-2 text-lg font-semibold tracking-tight">{{ t('app.name') }}</span>
                    </template>
                </Link>

                <!-- Desktop nav -->
                <nav class="hidden items-center gap-8 md:flex">
                    <Link
                        v-for="item in navLinks"
                        :key="item.href"
                        :href="item.href"
                        class="text-sm font-medium text-ink-600 transition hover:text-ink-900 dark:text-ink-400 dark:hover:text-white"
                    >
                        {{ t(item.label) }}
                    </Link>
                </nav>

                <div class="hidden items-center gap-3 md:flex">
                    <button type="button" :class="iconBtn" :aria-label="t('common.toggle_theme')" @click="toggleColorMode">
                        <SunIcon v-if="isDark" class="h-5 w-5" />
                        <MoonIcon v-else class="h-5 w-5" />
                    </button>
                    <Link :href="loginHref" class="text-sm font-medium text-ink-600 hover:text-ink-900 dark:text-ink-400 dark:hover:text-white">
                        {{ t('public.nav.login') }}
                    </Link>
                    <Link
                        :href="registerHref"
                        class="rounded-control bg-ink-950 px-4 py-2 text-sm font-semibold text-white shadow-subtle transition hover:bg-ink-800 dark:bg-ink-50 dark:text-ink-950 dark:hover:bg-ink-200"
                    >
                        {{ t('public.nav.register') }}
                    </Link>
                </div>

                <!-- Mobile toggle -->
                <div class="flex items-center gap-1 md:hidden">
                    <button type="button" :class="iconBtn" :aria-label="t('common.toggle_theme')" @click="toggleColorMode">
                        <SunIcon v-if="isDark" class="h-5 w-5" />
                        <MoonIcon v-else class="h-5 w-5" />
                    </button>
                    <button
                        type="button"
                        :class="iconBtn"
                        :aria-label="t('public.nav.menu')"
                        @click="mobileOpen = !mobileOpen"
                    >
                        <Bars3Icon v-if="!mobileOpen" class="h-6 w-6" />
                        <XMarkIcon v-else class="h-6 w-6" />
                    </button>
                </div>
            </div>

            <!-- Mobile menu -->
            <div v-show="mobileOpen" class="border-t border-ink-200 bg-white px-4 py-3 dark:border-ink-800 dark:bg-ink-950 md:hidden">
                <nav class="flex flex-col gap-1">
                    <Link
                        v-for="item in navLinks"
                        :key="item.href"
                        :href="item.href"
                        class="rounded-control px-3 py-2 text-sm font-medium text-ink-700 hover:bg-ink-100 dark:text-ink-200 dark:hover:bg-ink-800"
                        @click="mobileOpen = false"
                    >
                        {{ t(item.label) }}
                    </Link>
                    <div class="mt-2 flex flex-col gap-2 border-t border-ink-200 pt-3 dark:border-ink-800">
                        <Link :href="loginHref" class="rounded-control px-3 py-2 text-sm font-medium text-ink-700 hover:bg-ink-100 dark:text-ink-200 dark:hover:bg-ink-800">
                            {{ t('public.nav.login') }}
                        </Link>
                        <Link :href="registerHref" class="rounded-control bg-ink-950 px-3 py-2 text-center text-sm font-semibold text-white hover:bg-ink-800 dark:bg-ink-50 dark:text-ink-950 dark:hover:bg-ink-200">
                            {{ t('public.nav.register') }}
                        </Link>
                    </div>
                </nav>
            </div>
        </header>

        <!-- Page content -->
        <main class="flex-1">
            <slot />
        </main>

        <!-- Footer -->
        <footer class="border-t border-ink-200 bg-ink-50 dark:border-ink-800 dark:bg-ink-900/50">
            <div class="mx-auto grid w-full max-w-7xl gap-8 px-4 py-12 sm:px-6 md:grid-cols-3 lg:px-8">
                <div>
                    <div class="flex items-center">
                        <template v-if="logoLight || logoDark">
                            <img :src="logoLight" :alt="t('app.name')" class="h-7 w-auto max-w-40 object-contain object-left dark:hidden" />
                            <img :src="logoDark" :alt="t('app.name')" class="hidden h-7 w-auto max-w-40 object-contain object-left dark:block" />
                        </template>
                        <template v-else>
                            <span class="grid h-8 w-8 place-items-center rounded-control bg-ink-950 text-sm font-bold text-white dark:bg-ink-50 dark:text-ink-950">D</span>
                            <span class="ml-2 text-lg font-semibold">{{ t('app.name') }}</span>
                        </template>
                    </div>
                    <p class="mt-3 max-w-xs text-sm text-ink-500">{{ t('public.footer.tagline') }}</p>
                </div>

                <div>
                    <p class="text-sm font-semibold text-ink-900 dark:text-ink-100">{{ t('public.footer.product') }}</p>
                    <ul class="mt-3 space-y-2 text-sm text-ink-500">
                        <li><Link href="/pricing" class="hover:text-ink-900 dark:hover:text-white">{{ t('public.nav.pricing') }}</Link></li>
                        <li><Link href="/" class="hover:text-ink-900 dark:hover:text-white">{{ t('public.features.title') }}</Link></li>
                    </ul>
                </div>

                <div>
                    <p class="text-sm font-semibold text-ink-900 dark:text-ink-100">{{ t('public.footer.company') }}</p>
                    <ul class="mt-3 space-y-2 text-sm text-ink-500">
                        <li><Link href="/about" class="hover:text-ink-900 dark:hover:text-white">{{ t('public.nav.about') }}</Link></li>
                        <li><Link href="/contact" class="hover:text-ink-900 dark:hover:text-white">{{ t('public.nav.contact') }}</Link></li>
                    </ul>
                </div>
            </div>
            <div class="border-t border-ink-200 py-6 text-center text-xs text-ink-400 dark:border-ink-800">
                © {{ year }} {{ t('app.name') }}. {{ t('public.footer.rights') }}
            </div>
        </footer>
    </div>
</template>
