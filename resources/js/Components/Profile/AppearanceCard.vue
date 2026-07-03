<script setup>
// Color-mode preference card shared by all four guard profile pages. The
// TopBar sun/moon button already toggles the theme; this card exists for
// discoverability and to expose the explicit 'system' option. setMode()
// applies instantly, caches to localStorage AND persists to the profile via
// the per-guard endpoint (useColorMode handles all three).
import { useI18n } from 'vue-i18n';
import { useColorMode } from '@/composables/useColorMode';

const { t } = useI18n();
const { mode, setMode } = useColorMode();
const modes = ['light', 'dark', 'system'];
</script>

<template>
    <div class="rounded-card border border-ink-200 bg-white p-4 shadow-subtle dark:border-ink-800 dark:bg-ink-900">
        <h2 class="text-sm font-semibold text-ink-700 dark:text-ink-200">{{ t('profile.appearance') }}</h2>
        <p class="mt-1 text-xs text-ink-400">{{ t('profile.appearance_hint') }}</p>

        <div class="mt-4 inline-flex rounded-control border border-ink-200 bg-ink-50 p-0.5 dark:border-ink-800 dark:bg-ink-950">
            <button
                v-for="m in modes"
                :key="m"
                type="button"
                class="rounded-control px-4 py-1.5 text-sm font-medium transition-colors"
                :class="mode === m
                    ? 'bg-ink-950 text-white dark:bg-ink-50 dark:text-ink-950'
                    : 'text-ink-600 hover:text-ink-900 dark:text-ink-400 dark:hover:text-ink-100'"
                @click="setMode(m)"
            >
                {{ t(`profile.color_${m}`) }}
            </button>
        </div>
    </div>
</template>
