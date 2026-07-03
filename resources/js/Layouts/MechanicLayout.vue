<script setup>
// Mechanic portal shell. Mobile-first (mechanics work on phones/tablets in the
// workshop) with large touch targets. Simple top bar: prominent scan action,
// dashboard link, color toggle, logout. Uses the mechanic-guard auth payload
// (auth.mechanic). Deliberately separate from the other portals.
//
// NOTE: the "Scan" button leads to the workshop home (active jobs). The actual
// vehicle scan is the physical QR sticker opened by the phone camera
// (/mechanic/{slug}/scan/{token}); an in-app camera scanner is a later session.
import { computed, onMounted } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { QrCodeIcon, SunIcon, MoonIcon, ArrowRightOnRectangleIcon, UserCircleIcon } from '@heroicons/vue/24/outline';
import Toast from '@/Components/UI/Toast.vue';
import { useColorMode } from '@/composables/useColorMode';

const { t } = useI18n();
const page = usePage();

const slug = computed(() => page.props.tenant?.slug);
const mechanic = computed(() => page.props.auth?.mechanic ?? null);
const base = computed(() => `/mechanic/${slug.value}`);

function logout() {
    router.post(`${base.value}/logout`);
}

const { isDark, toggle: toggleColorMode, syncFromServer } = useColorMode();
onMounted(syncFromServer);

const iconBtn =
    'inline-flex h-11 w-11 items-center justify-center rounded-control text-ink-500 transition-colors hover:bg-ink-100 hover:text-ink-900 dark:hover:bg-ink-800 dark:hover:text-ink-100';
</script>

<template>
    <div class="min-h-screen bg-ink-50 text-ink-900 transition-colors dark:bg-ink-950 dark:text-ink-100">
        <header class="sticky top-0 z-30 border-b border-ink-200 bg-white/90 backdrop-blur dark:border-ink-800 dark:bg-ink-950/90">
            <div class="mx-auto flex h-16 w-full max-w-3xl items-center gap-3 px-4">
                <Link :href="`${base}/dashboard`" class="text-base font-semibold tracking-tight">
                    {{ t('workshop.portal_title') }}
                </Link>

                <div class="ml-auto flex items-center gap-2">
                    <button type="button" :class="iconBtn" :aria-label="t('common.toggle_theme')" @click="toggleColorMode">
                        <SunIcon v-if="isDark" class="h-5 w-5" />
                        <MoonIcon v-else class="h-5 w-5" />
                    </button>
                    <Link
                        v-if="mechanic"
                        :href="`${base}/profile`"
                        :class="iconBtn"
                        :aria-label="t('common.profile')"
                    >
                        <UserCircleIcon class="h-5 w-5" />
                    </Link>
                    <button
                        v-if="mechanic"
                        type="button"
                        :class="iconBtn"
                        :aria-label="t('common.logout')"
                        @click="logout"
                    >
                        <ArrowRightOnRectangleIcon class="h-5 w-5" />
                    </button>
                </div>
            </div>

            <!-- Prominent scan action (large touch target) -->
            <div v-if="mechanic" class="mx-auto w-full max-w-3xl px-4 pb-3">
                <Link
                    :href="`${base}/dashboard`"
                    class="flex h-14 items-center justify-center gap-3 rounded-card bg-ink-950 text-base font-semibold text-white transition-colors hover:bg-ink-800 dark:bg-ink-50 dark:text-ink-950 dark:hover:bg-ink-200"
                >
                    <QrCodeIcon class="h-6 w-6" />
                    {{ t('workshop.scan') }}
                </Link>
            </div>
        </header>

        <main class="mx-auto w-full max-w-3xl px-4 py-6">
            <slot />
        </main>

        <Toast />
    </div>
</template>
