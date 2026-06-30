<script setup>
// Public landing website shell. Wraps all CMS-controlled marketing pages plus
// the public auth pages (login/register). Top nav + footer + responsive mobile
// menu. FUNCTIONAL ONLY — design pass later.
//
// NOTE on auth links: tenant login is path-based (/app/{slug}/login) and has no
// global entry yet, so both "Log in" and "Get started" route to /register (the
// single global auth entry). A global login / workspace-resolver page is a
// later follow-up.
import { ref } from 'vue';
import { Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

const mobileOpen = ref(false);

const navLinks = [
    { href: '/pricing', label: 'public.nav.pricing' },
    { href: '/about', label: 'public.nav.about' },
    { href: '/contact', label: 'public.nav.contact' },
];

const registerHref = '/register';
const loginHref = '/register';

const year = new Date().getFullYear();
</script>

<template>
    <div class="flex min-h-screen flex-col bg-white text-slate-900 dark:bg-slate-950 dark:text-slate-100 transition-colors">
        <!-- Header -->
        <header class="sticky top-0 z-30 border-b border-slate-200/70 bg-white/80 backdrop-blur dark:border-slate-800/70 dark:bg-slate-950/80">
            <div class="mx-auto flex h-16 w-full max-w-7xl items-center justify-between px-4 sm:px-6 lg:px-8">
                <Link href="/" class="flex items-center gap-2">
                    <span class="grid h-8 w-8 place-items-center rounded-lg bg-indigo-600 text-sm font-bold text-white">D</span>
                    <span class="text-lg font-semibold tracking-tight">{{ t('app.name') }}</span>
                </Link>

                <!-- Desktop nav -->
                <nav class="hidden items-center gap-8 md:flex">
                    <Link
                        v-for="item in navLinks"
                        :key="item.href"
                        :href="item.href"
                        class="text-sm font-medium text-slate-600 transition hover:text-slate-900 dark:text-slate-300 dark:hover:text-white"
                    >
                        {{ t(item.label) }}
                    </Link>
                </nav>

                <div class="hidden items-center gap-3 md:flex">
                    <Link :href="loginHref" class="text-sm font-medium text-slate-600 hover:text-slate-900 dark:text-slate-300 dark:hover:text-white">
                        {{ t('public.nav.login') }}
                    </Link>
                    <Link
                        :href="registerHref"
                        class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-500"
                    >
                        {{ t('public.nav.register') }}
                    </Link>
                </div>

                <!-- Mobile toggle -->
                <button
                    type="button"
                    class="inline-flex items-center justify-center rounded-lg p-2 text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800 md:hidden"
                    :aria-label="t('public.nav.menu')"
                    @click="mobileOpen = !mobileOpen"
                >
                    <svg v-if="!mobileOpen" class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" />
                    </svg>
                    <svg v-else class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <!-- Mobile menu -->
            <div v-show="mobileOpen" class="border-t border-slate-200 bg-white px-4 py-3 dark:border-slate-800 dark:bg-slate-950 md:hidden">
                <nav class="flex flex-col gap-1">
                    <Link
                        v-for="item in navLinks"
                        :key="item.href"
                        :href="item.href"
                        class="rounded-lg px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-100 dark:text-slate-200 dark:hover:bg-slate-800"
                        @click="mobileOpen = false"
                    >
                        {{ t(item.label) }}
                    </Link>
                    <div class="mt-2 flex flex-col gap-2 border-t border-slate-200 pt-3 dark:border-slate-800">
                        <Link :href="loginHref" class="rounded-lg px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-100 dark:text-slate-200 dark:hover:bg-slate-800">
                            {{ t('public.nav.login') }}
                        </Link>
                        <Link :href="registerHref" class="rounded-lg bg-indigo-600 px-3 py-2 text-center text-sm font-semibold text-white hover:bg-indigo-500">
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
        <footer class="border-t border-slate-200 bg-slate-50 dark:border-slate-800 dark:bg-slate-900/50">
            <div class="mx-auto grid w-full max-w-7xl gap-8 px-4 py-12 sm:px-6 md:grid-cols-3 lg:px-8">
                <div>
                    <div class="flex items-center gap-2">
                        <span class="grid h-8 w-8 place-items-center rounded-lg bg-indigo-600 text-sm font-bold text-white">D</span>
                        <span class="text-lg font-semibold">{{ t('app.name') }}</span>
                    </div>
                    <p class="mt-3 max-w-xs text-sm text-slate-500 dark:text-slate-400">{{ t('public.footer.tagline') }}</p>
                </div>

                <div>
                    <p class="text-sm font-semibold text-slate-900 dark:text-slate-100">{{ t('public.footer.product') }}</p>
                    <ul class="mt-3 space-y-2 text-sm text-slate-500 dark:text-slate-400">
                        <li><Link href="/pricing" class="hover:text-slate-900 dark:hover:text-white">{{ t('public.nav.pricing') }}</Link></li>
                        <li><Link href="/" class="hover:text-slate-900 dark:hover:text-white">{{ t('public.features.title') }}</Link></li>
                    </ul>
                </div>

                <div>
                    <p class="text-sm font-semibold text-slate-900 dark:text-slate-100">{{ t('public.footer.company') }}</p>
                    <ul class="mt-3 space-y-2 text-sm text-slate-500 dark:text-slate-400">
                        <li><Link href="/about" class="hover:text-slate-900 dark:hover:text-white">{{ t('public.nav.about') }}</Link></li>
                        <li><Link href="/contact" class="hover:text-slate-900 dark:hover:text-white">{{ t('public.nav.contact') }}</Link></li>
                    </ul>
                </div>
            </div>
            <div class="border-t border-slate-200 py-6 text-center text-xs text-slate-400 dark:border-slate-800">
                © {{ year }} {{ t('app.name') }}. {{ t('public.footer.rights') }}
            </div>
        </footer>
    </div>
</template>
