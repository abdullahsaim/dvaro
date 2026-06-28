<script setup>
// Mechanic dashboard — this mechanic's open jobs + recently completed.
// FUNCTIONAL ONLY — design pass later.
import { computed } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import MechanicLayout from '@/Layouts/MechanicLayout.vue';

const props = defineProps({
    activeJobs: { type: Array, required: true },
    recentCompleted: { type: Array, required: true },
});

const { t } = useI18n();
const page = usePage();
const base = computed(() => `/mechanic/${page.props.tenant.slug}`);

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

        <h1 class="text-2xl font-semibold">{{ t('workshop.dashboard') }}</h1>

        <!-- Active jobs -->
        <section class="mt-6">
            <h2 class="text-sm font-medium uppercase tracking-wide text-slate-500 dark:text-slate-400">
                {{ t('workshop.active_jobs') }}
            </h2>
            <p v-if="!activeJobs.length" class="mt-3 text-sm text-slate-500 dark:text-slate-400">
                {{ t('workshop.no_active_jobs') }}
            </p>
            <ul v-else class="mt-3 space-y-2">
                <li
                    v-for="job in activeJobs"
                    :key="job.id"
                    class="rounded border border-slate-200 p-4 dark:border-slate-800"
                >
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <p class="font-medium">{{ job.title }}</p>
                            <p class="text-sm text-slate-500 dark:text-slate-400">{{ vehicleLabel(job) }}</p>
                        </div>
                        <div class="flex items-center gap-3">
                            <span class="rounded-full bg-slate-100 px-2.5 py-0.5 text-xs dark:bg-slate-800">
                                {{ t(`workshop.statuses.${job.status}`) }}
                            </span>
                            <Link
                                v-if="vehicleUrl(job)"
                                :href="vehicleUrl(job)"
                                class="text-sm text-indigo-600 hover:underline dark:text-indigo-400"
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
            <h2 class="text-sm font-medium uppercase tracking-wide text-slate-500 dark:text-slate-400">
                {{ t('workshop.recent_completed') }}
            </h2>
            <p v-if="!recentCompleted.length" class="mt-3 text-sm text-slate-500 dark:text-slate-400">
                {{ t('workshop.no_completed') }}
            </p>
            <ul v-else class="mt-3 space-y-2">
                <li
                    v-for="job in recentCompleted"
                    :key="job.id"
                    class="flex items-center justify-between rounded border border-slate-200 p-4 text-sm dark:border-slate-800"
                >
                    <span>{{ job.title }}</span>
                    <span class="text-slate-500 dark:text-slate-400">{{ vehicleLabel(job) }}</span>
                </li>
            </ul>
        </section>
    </MechanicLayout>
</template>
