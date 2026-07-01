<script setup>
// Mechanic dashboard — this mechanic's open jobs + recently completed.
// Design-system pass; mobile-first job list.
import { computed } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import MechanicLayout from '@/Layouts/MechanicLayout.vue';
import StatusBadge from '@/Components/UI/StatusBadge.vue';

const props = defineProps({
    activeJobs: { type: Array, required: true },
    recentCompleted: { type: Array, required: true },
});

const { t } = useI18n();
const page = usePage();
const base = computed(() => `/mechanic/${page.props.tenant.slug}`);

const statusVariants = {
    pending: 'neutral',
    in_progress: 'info',
    completed: 'success',
    waiting_for_parts: 'warning',
    re_inspection_required: 'danger',
};

function vehicleUrl(job) {
    return job.vehicle?.qr_code_token
        ? `${base.value}/vehicle/${job.vehicle.qr_code_token}`
        : null;
}

function vehicleLabel(job) {
    const v = job.vehicle;
    return v ? `${v.make} ${v.model} · ${v.registration_number}` : '—';
}
</script>

<template>
    <MechanicLayout>
        <Head :title="t('workshop.dashboard')" />

        <h1 class="text-2xl font-semibold tracking-tight text-ink-900 dark:text-ink-50">{{ t('workshop.dashboard') }}</h1>

        <!-- Active jobs -->
        <section class="mt-6">
            <h2 class="text-xs font-medium uppercase tracking-wide text-ink-500">{{ t('workshop.active_jobs') }}</h2>
            <p v-if="!activeJobs.length" class="mt-3 text-sm text-ink-500">{{ t('workshop.no_active_jobs') }}</p>
            <ul v-else class="mt-3 space-y-2">
                <li
                    v-for="job in activeJobs"
                    :key="job.id"
                    class="rounded-card border border-ink-200 bg-white p-4 shadow-subtle dark:border-ink-800 dark:bg-ink-900"
                >
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <p class="font-medium text-ink-900 dark:text-ink-50">{{ job.title }}</p>
                            <p class="text-sm text-ink-500">{{ vehicleLabel(job) }}</p>
                        </div>
                        <div class="flex items-center gap-3">
                            <StatusBadge :variant="statusVariants[job.status]" :label="t(`workshop.statuses.${job.status}`)" />
                            <Link
                                v-if="vehicleUrl(job)"
                                :href="vehicleUrl(job)"
                                class="text-sm font-medium text-ink-900 hover:underline dark:text-ink-100"
                            >
                                {{ t('workshop.open_qr') }}
                            </Link>
                        </div>
                    </div>
                </li>
            </ul>
        </section>

        <!-- Recently completed -->
        <section class="mt-8">
            <h2 class="text-xs font-medium uppercase tracking-wide text-ink-500">{{ t('workshop.recent_completed') }}</h2>
            <p v-if="!recentCompleted.length" class="mt-3 text-sm text-ink-500">{{ t('workshop.no_completed') }}</p>
            <ul v-else class="mt-3 space-y-2">
                <li
                    v-for="job in recentCompleted"
                    :key="job.id"
                    class="flex items-center justify-between rounded-card border border-ink-200 bg-white p-4 text-sm shadow-subtle dark:border-ink-800 dark:bg-ink-900"
                >
                    <span class="text-ink-900 dark:text-ink-50">{{ job.title }}</span>
                    <span class="text-ink-500">{{ vehicleLabel(job) }}</span>
                </li>
            </ul>
        </section>
    </MechanicLayout>
</template>
