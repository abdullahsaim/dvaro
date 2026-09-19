<script setup>
// Number-plate lookup for the mechanic portal (manual alternative to QR).
// Large touch target, uppercase + no autocorrect — mechanics type this on a
// phone in the workshop. GET mechanic/{slug}/vehicles/search?plate=
import { ref } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { MagnifyingGlassIcon } from '@heroicons/vue/24/outline';
import Button from '@/Components/UI/Button.vue';

const props = defineProps({
    initial: { type: String, default: '' },
});

const { t } = useI18n();
const page = usePage();
const plate = ref(props.initial);
const searching = ref(false);

function search() {
    if (!plate.value.trim()) return;
    router.get(
        `/mechanic/${page.props.tenant.slug}/vehicles/search`,
        { plate: plate.value.trim() },
        {
            onStart: () => (searching.value = true),
            onFinish: () => (searching.value = false),
        },
    );
}
</script>

<template>
    <form role="search" class="flex gap-2" @submit.prevent="search">
        <label class="relative flex-1">
            <span class="sr-only">{{ t('workshop.plate_search.label') }}</span>
            <MagnifyingGlassIcon class="pointer-events-none absolute left-3 top-1/2 h-5 w-5 -translate-y-1/2 text-ink-400" aria-hidden="true" />
            <input
                v-model="plate"
                type="search"
                autocapitalize="characters"
                autocomplete="off"
                autocorrect="off"
                spellcheck="false"
                :placeholder="t('workshop.plate_search.placeholder')"
                class="h-11 w-full rounded-control border border-ink-200 bg-white pl-10 pr-3 text-base uppercase tracking-wide text-ink-900 transition-colors placeholder:normal-case placeholder:tracking-normal placeholder:text-ink-400 focus:border-ink-900 focus:outline-none focus:ring-2 focus:ring-ink-900/10 dark:border-ink-800 dark:bg-ink-900 dark:text-ink-50 dark:focus:border-ink-100 dark:focus:ring-ink-100/10"
            />
        </label>
        <Button type="submit" size="lg" :loading="searching">{{ t('workshop.plate_search.submit') }}</Button>
    </form>
</template>
