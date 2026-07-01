<script setup>
// App top bar: sidebar toggles (collapse on desktop, open drawer on mobile),
// context name, color-mode toggle, notification placeholder, and a user menu.
import { ref, computed } from 'vue';
import { router, Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import {
    Bars3Icon,
    SunIcon,
    MoonIcon,
    BellIcon,
    ChevronDownIcon,
    ArrowRightOnRectangleIcon,
    UserCircleIcon,
} from '@heroicons/vue/24/outline';
import { useSidebar } from '@/composables/useSidebar';
import { useColorMode } from '@/composables/useColorMode';

const props = defineProps({
    title: { type: String, default: '' }, // context name (tenant / "Platform")
    user: { type: Object, default: null }, // { name, email, role }
    logoutHref: { type: String, default: '' },
    profileHref: { type: String, default: '' },
});

const { t } = useI18n();
const { toggleCollapsed, openMobile } = useSidebar();
const { isDark, toggle: toggleColorMode } = useColorMode();

const menuOpen = ref(false);

const initials = computed(() => {
    const name = props.user?.name || props.user?.email || '?';
    return name
        .split(/\s+/)
        .map((p) => p.charAt(0))
        .slice(0, 2)
        .join('')
        .toUpperCase();
});

function logout() {
    menuOpen.value = false;
    if (props.logoutHref) router.post(props.logoutHref);
}

const iconBtn =
    'inline-flex h-9 w-9 items-center justify-center rounded-control text-ink-500 transition-colors hover:bg-ink-100 hover:text-ink-900 dark:hover:bg-ink-800 dark:hover:text-ink-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ink-950 dark:focus-visible:ring-ink-100';
</script>

<template>
    <header class="sticky top-0 z-30 flex h-16 items-center gap-2 border-b border-ink-200 bg-white/80 px-4 backdrop-blur dark:border-ink-800 dark:bg-ink-950/80">
        <!-- Mobile: open drawer -->
        <button type="button" :class="[iconBtn, 'md:hidden']" :aria-label="t('common.menu')" @click="openMobile">
            <Bars3Icon class="h-5 w-5" />
        </button>
        <!-- Desktop: collapse toggle -->
        <button type="button" :class="[iconBtn, 'hidden md:inline-flex']" :aria-label="t('common.toggle_sidebar')" @click="toggleCollapsed">
            <Bars3Icon class="h-5 w-5" />
        </button>

        <p v-if="title" class="ml-1 truncate text-sm font-semibold text-ink-900 dark:text-ink-50">{{ title }}</p>

        <div class="ml-auto flex items-center gap-1">
            <!-- Color mode -->
            <button type="button" :class="iconBtn" :aria-label="t('common.toggle_theme')" @click="toggleColorMode">
                <SunIcon v-if="isDark" class="h-5 w-5" />
                <MoonIcon v-else class="h-5 w-5" />
            </button>

            <!-- Notifications (placeholder for a future feature) -->
            <button type="button" :class="iconBtn" :aria-label="t('common.notifications')">
                <BellIcon class="h-5 w-5" />
            </button>

            <!-- User menu -->
            <div v-if="user" class="relative ml-1">
                <button
                    type="button"
                    class="flex items-center gap-2 rounded-control py-1 pl-1 pr-2 transition-colors hover:bg-ink-100 dark:hover:bg-ink-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ink-950 dark:focus-visible:ring-ink-100"
                    @click="menuOpen = !menuOpen"
                >
                    <span class="flex h-8 w-8 items-center justify-center rounded-full bg-ink-950 text-xs font-semibold text-white dark:bg-ink-50 dark:text-ink-950">
                        {{ initials }}
                    </span>
                    <ChevronDownIcon class="hidden h-4 w-4 text-ink-400 sm:block" />
                </button>

                <!-- Backdrop to capture outside clicks -->
                <button v-if="menuOpen" type="button" class="fixed inset-0 z-40 cursor-default" tabindex="-1" aria-hidden="true" @click="menuOpen = false" />

                <Transition
                    enter-active-class="transition duration-150 ease-out"
                    enter-from-class="opacity-0 scale-95"
                    leave-active-class="transition duration-100 ease-in"
                    leave-to-class="opacity-0 scale-95"
                >
                    <div
                        v-if="menuOpen"
                        class="absolute right-0 z-50 mt-2 w-56 origin-top-right overflow-hidden rounded-card border border-ink-200 bg-white py-1 shadow-pop dark:border-ink-800 dark:bg-ink-900"
                    >
                        <div class="border-b border-ink-100 px-4 py-2.5 dark:border-ink-800">
                            <p class="truncate text-sm font-medium text-ink-900 dark:text-ink-50">{{ user.name || user.email }}</p>
                            <p v-if="user.email && user.name" class="truncate text-xs text-ink-500">{{ user.email }}</p>
                        </div>
                        <Link
                            v-if="profileHref"
                            :href="profileHref"
                            class="flex items-center gap-2.5 px-4 py-2 text-sm text-ink-700 transition-colors hover:bg-ink-50 dark:text-ink-300 dark:hover:bg-ink-800"
                            @click="menuOpen = false"
                        >
                            <UserCircleIcon class="h-4 w-4" />
                            {{ t('common.profile') }}
                        </Link>
                        <button
                            v-if="logoutHref"
                            type="button"
                            class="flex w-full items-center gap-2.5 px-4 py-2 text-left text-sm text-ink-700 transition-colors hover:bg-ink-50 dark:text-ink-300 dark:hover:bg-ink-800"
                            @click="logout"
                        >
                            <ArrowRightOnRectangleIcon class="h-4 w-4" />
                            {{ t('common.logout') }}
                        </button>
                    </div>
                </Transition>
            </div>
        </div>
    </header>
</template>
