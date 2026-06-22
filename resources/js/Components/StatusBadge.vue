<script setup>
// Color-coded vehicle status badge. Label is translated; colors are fixed by
// the status key (CLAUDE.md / session spec):
//   available=green, rented=blue, maintenance=yellow,
//   suspended=gray, accident=red, reserved=purple
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    status: { type: String, required: true },
});

const { t } = useI18n();

const colorMap = {
    available: 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300',
    rented: 'bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-300',
    maintenance: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/40 dark:text-yellow-300',
    suspended: 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300',
    accident: 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-300',
    reserved: 'bg-purple-100 text-purple-800 dark:bg-purple-900/40 dark:text-purple-300',
};

const classes = computed(() => colorMap[props.status] ?? colorMap.suspended);
const label = computed(() => t(`fleet.statuses.${props.status}`));
</script>

<template>
    <span
        class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium"
        :class="classes"
    >
        {{ label }}
    </span>
</template>
