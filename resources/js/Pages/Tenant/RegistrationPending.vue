<script setup>
// Shown after a self-registration when the platform requires manual tenant
// approval (Super Admin → Settings → "Require manual approval of new
// tenants"). No login happened — the account is real but blocked by
// TenantMiddleware until a super admin approves it. Carries no tenant-scoped
// data, matching the minimal standalone style of Tenant/Register.vue.
import { computed } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { SunIcon, MoonIcon } from '@heroicons/vue/24/outline';
import { useColorMode } from '@/composables/useColorMode';
import { cmsImage } from '@/cms/defaultImages.js';

const { t } = useI18n();
const page = usePage();
const { isDark, toggle: toggleColorMode } = useColorMode();

const branding = computed(() => page.props.branding ?? {});
const logoLight = computed(
    () => cmsImage('logo_light', branding.value.logo_light) || cmsImage('logo_dark', branding.value.logo_dark),
);
const logoDark = computed(
    () => cmsImage('logo_dark', branding.value.logo_dark) || cmsImage('logo_light', branding.value.logo_light),
);

const iconBtn =
    'inline-flex h-9 w-9 items-center justify-center rounded-control text-ink-500 transition-colors hover:bg-ink-100 hover:text-ink-900 dark:hover:bg-ink-800 dark:hover:text-ink-100';
</script>

<template>
    <Head :title="t('auth.pending.title')" />

    <div class="flex min-h-screen flex-col bg-white text-ink-900 transition-colors dark:bg-ink-950 dark:text-ink-100">
        <header class="flex h-16 items-center justify-between px-4 sm:px-6 lg:px-8">
            <Link href="/" class="flex items-center">
                <template v-if="logoLight || logoDark">
                    <img :src="logoLight" :alt="t('app.name')" class="h-7 w-auto max-w-40 object-contain object-left dark:hidden" />
                    <img :src="logoDark" :alt="t('app.name')" class="hidden h-7 w-auto max-w-40 object-contain object-left dark:block" />
                </template>
                <template v-else>
                    <span class="grid h-8 w-8 place-items-center rounded-control bg-ink-950 text-sm font-bold text-white dark:bg-ink-50 dark:text-ink-950">D</span>
                    <span class="ml-2 text-lg font-semibold tracking-tight">{{ t('app.name') }}</span>
                </template>
            </Link>

            <button type="button" :class="iconBtn" :aria-label="t('common.toggle_theme')" @click="toggleColorMode">
                <SunIcon v-if="isDark" class="h-5 w-5" />
                <MoonIcon v-else class="h-5 w-5" />
            </button>
        </header>

        <div class="mx-auto mt-16 max-w-md px-4 text-center">
            <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-info-50 dark:bg-info-900">
                <svg class="h-7 w-7 text-info-600 dark:text-info-100" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6l4 2M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                </svg>
            </div>

            <h1 class="mt-6 text-xl font-semibold text-ink-900 dark:text-ink-50">{{ t('auth.pending.title') }}</h1>
            <p class="mt-2 text-sm text-ink-500">{{ t('auth.pending.body') }}</p>
            <p class="mt-4 text-sm text-ink-500">{{ t('auth.pending.email_hint') }}</p>

            <div class="mt-8">
                <Link href="/" class="text-sm font-semibold text-ink-900 hover:underline dark:text-ink-100">
                    {{ t('auth.pending.back_home') }}
                </Link>
            </div>
        </div>
    </div>
</template>
