<script setup>
// Personal settings card, shared by all four guard profile pages: where this
// person lands after signing in, how many rows they want per page, and their
// own opt-outs. Nothing here affects anyone else in the company.
//
// Options come from the server (UserPreferences), which also re-validates
// everything it is sent — this form can never widen what it controls.
import { computed } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import Select from '@/Components/UI/Select.vue';
import Button from '@/Components/UI/Button.vue';

const props = defineProps({
    action: { type: String, required: true },
    preferences: { type: Object, required: true },
    landingPages: { type: Array, default: () => ['dashboard'] },
    rowsPerPageOptions: { type: Array, default: () => [15, 25, 50, 100] },
    // Tenant staff only: the daily fleet digest opt-out.
    showDigestOptOut: { type: Boolean, default: false },
});

const { t } = useI18n();

const form = useForm({
    preferences: {
        landing_page: props.preferences.landing_page ?? props.landingPages[0],
        rows_per_page: props.preferences.rows_per_page,
        mute_fleet_digest: props.preferences.mute_fleet_digest,
    },
});

// One landing page means there is nothing to choose.
const canChooseLanding = computed(() => props.landingPages.length > 1);

function save() {
    form.put(props.action, { preserveScroll: true });
}
</script>

<template>
    <form
        class="rounded-card border border-ink-200 bg-white p-4 shadow-subtle dark:border-ink-800 dark:bg-ink-900"
        @submit.prevent="save"
    >
        <h2 class="text-sm font-semibold text-ink-700 dark:text-ink-200">{{ t('profile.preferences') }}</h2>
        <p class="mt-1 text-xs text-ink-400">{{ t('profile.preferences_hint') }}</p>

        <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
            <Select
                v-if="canChooseLanding"
                v-model="form.preferences.landing_page"
                :label="t('profile.landing_page')"
                :help="t('profile.landing_page_help')"
            >
                <option v-for="p in landingPages" :key="p" :value="p">{{ t(`profile.landing.${p}`) }}</option>
            </Select>

            <Select
                v-model="form.preferences.rows_per_page"
                :label="t('profile.rows_per_page')"
                :help="t('profile.rows_per_page_help')"
            >
                <option v-for="n in rowsPerPageOptions" :key="n" :value="n">{{ n }}</option>
            </Select>
        </div>

        <label v-if="showDigestOptOut" class="mt-4 flex items-start gap-3">
            <input
                v-model="form.preferences.mute_fleet_digest"
                type="checkbox"
                class="mt-0.5 h-4 w-4 rounded border-ink-300 accent-ink-900 dark:border-ink-700 dark:accent-ink-100"
            />
            <span class="text-sm text-ink-600 dark:text-ink-300">
                {{ t('profile.mute_fleet_digest') }}
                <span class="block text-xs text-ink-400">{{ t('profile.mute_fleet_digest_help') }}</span>
            </span>
        </label>

        <div class="mt-4">
            <Button type="submit" :loading="form.processing">{{ t('common.save') }}</Button>
        </div>
    </form>
</template>
