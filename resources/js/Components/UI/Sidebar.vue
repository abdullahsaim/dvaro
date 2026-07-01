<script setup>
// Collapsible icon sidebar (desktop) + slide-over drawer (mobile).
//   - Collapsed by default; reveal labels via an explicit toggle (in TopBar),
//     never hover. Collapsed state persists via useSidebar() → localStorage.
//   - Collapsed: icons only, label shown as a hover tooltip.
//   - Expanded: icons + labels. Active route highlighted. Smooth width animation.
//
// `variant`: 'default' (theme-aware white/ink) or 'dark' (forced dark rail — a
// monochrome cue that you're in a distinct context, e.g. the super admin panel).
//
// `items` = [{ key, label, href, icon (component), active (bool) }].
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { useSidebar } from '@/composables/useSidebar';

const props = defineProps({
    items: { type: Array, required: true },
    title: { type: String, default: 'DVARO' },
    contextLabel: { type: String, default: '' },
    variant: { type: String, default: 'default' }, // 'default' | 'dark'
});

const { collapsed, mobileOpen, closeMobile } = useSidebar();

const isDark = computed(() => props.variant === 'dark');

const ui = computed(() =>
    isDark.value
        ? {
              shell: 'border-ink-800 bg-ink-950',
              mark: 'bg-ink-50 text-ink-950',
              title: 'text-ink-50',
              sub: 'text-ink-400',
              active: 'bg-ink-800 text-white',
              idle: 'text-ink-400 hover:bg-ink-800/70 hover:text-ink-100',
          }
        : {
              shell: 'border-ink-200 bg-white dark:border-ink-800 dark:bg-ink-950',
              mark: 'bg-ink-950 text-white dark:bg-ink-50 dark:text-ink-950',
              title: 'text-ink-900 dark:text-ink-50',
              sub: 'text-ink-500',
              active: 'bg-ink-100 text-ink-900 dark:bg-ink-800 dark:text-ink-50',
              idle: 'text-ink-600 hover:bg-ink-100 hover:text-ink-900 dark:text-ink-400 dark:hover:bg-ink-800 dark:hover:text-ink-100',
          },
);

const linkBase = 'group relative flex items-center gap-3 rounded-control px-3 py-2 text-sm font-medium transition-colors';
</script>

<template>
    <!-- ── Desktop sidebar ──────────────────────────────────────────────── -->
    <aside
        class="hidden shrink-0 border-r transition-[width] duration-200 ease-smooth md:flex md:flex-col"
        :class="[ui.shell, collapsed ? 'w-18' : 'w-64']"
    >
        <!-- Brand -->
        <div class="flex h-16 items-center gap-2.5 px-4">
            <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-control text-sm font-bold" :class="ui.mark">
                {{ title.charAt(0) }}
            </div>
            <div v-if="!collapsed" class="min-w-0">
                <p class="truncate text-sm font-semibold" :class="ui.title">{{ title }}</p>
                <p v-if="contextLabel" class="truncate text-xs" :class="ui.sub">{{ contextLabel }}</p>
            </div>
        </div>

        <!-- Nav -->
        <nav class="flex-1 space-y-1 overflow-y-auto px-3 py-2">
            <Link
                v-for="item in items"
                :key="item.key"
                :href="item.href"
                :class="[linkBase, item.active ? ui.active : ui.idle, collapsed ? 'justify-center px-0' : '']"
            >
                <component :is="item.icon" class="h-5 w-5 shrink-0" />
                <span v-if="!collapsed" class="truncate">{{ item.label }}</span>

                <!-- Tooltip when collapsed -->
                <span
                    v-if="collapsed"
                    class="pointer-events-none absolute left-full z-30 ml-2 hidden whitespace-nowrap rounded-control bg-ink-950 px-2 py-1 text-xs font-medium text-white shadow-pop group-hover:block dark:bg-ink-100 dark:text-ink-950"
                >
                    {{ item.label }}
                </span>
            </Link>
        </nav>
    </aside>

    <!-- ── Mobile drawer ────────────────────────────────────────────────── -->
    <Teleport to="body">
        <Transition
            enter-active-class="transition-opacity duration-200"
            enter-from-class="opacity-0"
            leave-active-class="transition-opacity duration-150"
            leave-to-class="opacity-0"
        >
            <div v-if="mobileOpen" class="fixed inset-0 z-50 md:hidden" @click="closeMobile">
                <div class="absolute inset-0 bg-ink-950/40 backdrop-blur-sm dark:bg-ink-950/60" />
            </div>
        </Transition>
        <Transition
            enter-active-class="transition-transform duration-250 ease-smooth"
            enter-from-class="-translate-x-full"
            leave-active-class="transition-transform duration-200 ease-smooth"
            leave-to-class="-translate-x-full"
        >
            <aside
                v-if="mobileOpen"
                class="fixed inset-y-0 left-0 z-50 flex w-64 flex-col border-r md:hidden"
                :class="ui.shell"
            >
                <div class="flex h-16 items-center gap-2.5 px-4">
                    <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-control text-sm font-bold" :class="ui.mark">
                        {{ title.charAt(0) }}
                    </div>
                    <div class="min-w-0">
                        <p class="truncate text-sm font-semibold" :class="ui.title">{{ title }}</p>
                        <p v-if="contextLabel" class="truncate text-xs" :class="ui.sub">{{ contextLabel }}</p>
                    </div>
                </div>
                <nav class="flex-1 space-y-1 overflow-y-auto px-3 py-2">
                    <Link
                        v-for="item in items"
                        :key="item.key"
                        :href="item.href"
                        :class="[linkBase, item.active ? ui.active : ui.idle]"
                        @click="closeMobile"
                    >
                        <component :is="item.icon" class="h-5 w-5 shrink-0" />
                        <span class="truncate">{{ item.label }}</span>
                    </Link>
                </nav>
            </aside>
        </Transition>
    </Teleport>
</template>
