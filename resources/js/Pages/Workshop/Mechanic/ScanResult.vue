<script setup>
// Mechanic vehicle service page (reached from a QR scan). Shows the vehicle,
// its service history, a new-log form, and per-log status + parts controls.
// FUNCTIONAL ONLY — design pass later.
import { computed, reactive } from 'vue';
import { Head, useForm, router, usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import MechanicLayout from '@/Layouts/MechanicLayout.vue';
import { useCurrency } from '@/composables/useCurrency';

const { formatAUD, toCents } = useCurrency();

const props = defineProps({
    vehicle: { type: Object, required: true },
    token: { type: String, required: true },
    serviceHistory: { type: Array, required: true },
    statuses: { type: Array, required: true },
});

const { t } = useI18n();
const page = usePage();
const base = computed(() => `/mechanic/${page.props.tenant.slug}`);

// New service log.
const logForm = useForm({
    token: props.token,
    title: '',
    description: '',
    odometer_reading: '',
    labour_cost: '', // AUD in the input → cents on submit
});

function createLog() {
    logForm.transform((data) => ({
        ...data,
        odometer_reading: data.odometer_reading === '' ? null : Number(data.odometer_reading),
        labour_cost: data.labour_cost === '' ? 0 : toCents(data.labour_cost),
    })).post(`${base.value}/logs`, {
        preserveScroll: true,
        onSuccess: () => logForm.reset('title', 'description', 'odometer_reading', 'labour_cost'),
    });
}

function changeStatus(log, status) {
    router.put(`${base.value}/logs/${log.id}/status`, { status }, { preserveScroll: true });
}

// Per-log "add part" inputs, keyed by log id.
const partInputs = reactive({});
function partInput(id) {
    if (!partInputs[id]) {
        partInputs[id] = { name: '', quantity: 1, unit_cost: '' };
    }
    return partInputs[id];
}

function addPart(log) {
    const input = partInput(log.id);
    router.post(
        `${base.value}/logs/${log.id}/parts`,
        {
            name: input.name,
            quantity: Number(input.quantity),
            unit_cost: input.unit_cost === '' ? 0 : toCents(input.unit_cost),
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                input.name = '';
                input.quantity = 1;
                input.unit_cost = '';
            },
        },
    );
}
</script>

<template>
    <MechanicLayout>
        <Head :title="`${vehicle.make} ${vehicle.model}`" />

        <!-- Vehicle header -->
        <div class="rounded border border-slate-200 p-4 dark:border-slate-800">
            <h1 class="text-2xl font-semibold">{{ vehicle.make }} {{ vehicle.model }}</h1>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                {{ vehicle.registration_number }} · {{ vehicle.year }} ·
                {{ t(`fleet.statuses.${vehicle.status}`) }}
            </p>
        </div>

        <!-- New service log -->
        <section class="mt-6 rounded border border-slate-200 p-4 dark:border-slate-800">
            <h2 class="text-sm font-medium uppercase tracking-wide text-slate-500 dark:text-slate-400">
                {{ t('workshop.new_log') }}
            </h2>
            <form class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-2" @submit.prevent="createLog">
                <label class="block sm:col-span-2">
                    <span class="text-sm">{{ t('workshop.fields.title') }}</span>
                    <input v-model="logForm.title" type="text" required
                        class="mt-1 w-full rounded border border-slate-300 px-3 py-2 dark:border-slate-700 dark:bg-slate-900" />
                    <p v-if="logForm.errors.title" class="mt-1 text-sm text-red-600">{{ logForm.errors.title }}</p>
                </label>
                <label class="block sm:col-span-2">
                    <span class="text-sm">{{ t('workshop.fields.description') }}</span>
                    <textarea v-model="logForm.description" rows="2"
                        class="mt-1 w-full rounded border border-slate-300 px-3 py-2 dark:border-slate-700 dark:bg-slate-900" />
                </label>
                <label class="block">
                    <span class="text-sm">{{ t('workshop.fields.odometer_reading') }}</span>
                    <input v-model="logForm.odometer_reading" type="number" min="0"
                        class="mt-1 w-full rounded border border-slate-300 px-3 py-2 dark:border-slate-700 dark:bg-slate-900" />
                </label>
                <label class="block">
                    <span class="text-sm">{{ t('workshop.fields.labour_cost') }}</span>
                    <input v-model="logForm.labour_cost" type="number" min="0" step="0.01"
                        class="mt-1 w-full rounded border border-slate-300 px-3 py-2 dark:border-slate-700 dark:bg-slate-900" />
                </label>
                <div class="sm:col-span-2">
                    <button type="submit" :disabled="logForm.processing"
                        class="rounded bg-indigo-600 px-4 py-2 text-sm text-white disabled:opacity-50">
                        {{ t('workshop.new_log') }}
                    </button>
                </div>
            </form>
        </section>

        <!-- Service history -->
        <section class="mt-8">
            <h2 class="text-sm font-medium uppercase tracking-wide text-slate-500 dark:text-slate-400">
                {{ t('workshop.service_history') }}
            </h2>
            <p v-if="!serviceHistory.length" class="mt-3 text-sm text-slate-500 dark:text-slate-400">
                {{ t('workshop.no_history') }}
            </p>

            <ul v-else class="mt-3 space-y-4">
                <li v-for="log in serviceHistory" :key="log.id"
                    class="rounded border border-slate-200 p-4 dark:border-slate-800">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <p class="font-medium">{{ log.title }}</p>
                            <p v-if="log.description" class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                                {{ log.description }}
                            </p>
                            <p class="mt-1 text-xs text-slate-400">
                                {{ log.mechanic?.name }}
                            </p>
                        </div>
                        <span class="rounded-full bg-slate-100 px-2.5 py-0.5 text-xs dark:bg-slate-800">
                            {{ t(`workshop.statuses.${log.status}`) }}
                        </span>
                    </div>

                    <!-- Costs -->
                    <dl class="mt-3 flex flex-wrap gap-x-6 gap-y-1 text-sm">
                        <div><dt class="inline text-slate-500 dark:text-slate-400">{{ t('workshop.labour') }}:</dt>
                            <dd class="inline font-medium"> {{ formatAUD(log.labour_cost) }}</dd></div>
                        <div><dt class="inline text-slate-500 dark:text-slate-400">{{ t('workshop.total') }}:</dt>
                            <dd class="inline font-medium"> {{ formatAUD(log.total_cost) }}</dd></div>
                    </dl>

                    <!-- Parts -->
                    <div class="mt-3">
                        <p class="text-xs font-medium uppercase tracking-wide text-slate-400">{{ t('workshop.parts') }}</p>
                        <p v-if="!log.parts?.length" class="text-sm text-slate-500 dark:text-slate-400">
                            {{ t('workshop.no_parts') }}
                        </p>
                        <ul v-else class="mt-1 space-y-1 text-sm">
                            <li v-for="part in log.parts" :key="part.id" class="flex justify-between">
                                <span>{{ part.name }} × {{ part.quantity }}</span>
                                <span class="text-slate-500 dark:text-slate-400">{{ formatAUD(part.total_cost) }}</span>
                            </li>
                        </ul>

                        <!-- Add part -->
                        <form class="mt-2 flex flex-wrap items-end gap-2" @submit.prevent="addPart(log)">
                            <label class="block">
                                <span class="text-xs text-slate-500 dark:text-slate-400">{{ t('workshop.fields.part_name') }}</span>
                                <input v-model="partInput(log.id).name" type="text" required
                                    class="mt-1 rounded border border-slate-300 px-2 py-1 text-sm dark:border-slate-700 dark:bg-slate-900" />
                            </label>
                            <label class="block">
                                <span class="text-xs text-slate-500 dark:text-slate-400">{{ t('workshop.fields.quantity') }}</span>
                                <input v-model="partInput(log.id).quantity" type="number" min="1"
                                    class="mt-1 w-20 rounded border border-slate-300 px-2 py-1 text-sm dark:border-slate-700 dark:bg-slate-900" />
                            </label>
                            <label class="block">
                                <span class="text-xs text-slate-500 dark:text-slate-400">{{ t('workshop.fields.unit_cost') }}</span>
                                <input v-model="partInput(log.id).unit_cost" type="number" min="0" step="0.01"
                                    class="mt-1 w-28 rounded border border-slate-300 px-2 py-1 text-sm dark:border-slate-700 dark:bg-slate-900" />
                            </label>
                            <button type="submit"
                                class="rounded bg-slate-700 px-3 py-1.5 text-sm text-white dark:bg-slate-300 dark:text-slate-900">
                                {{ t('workshop.add_part') }}
                            </button>
                        </form>
                    </div>

                    <!-- Status change -->
                    <div class="mt-3 flex flex-wrap items-center gap-2">
                        <span class="text-xs text-slate-500 dark:text-slate-400">{{ t('workshop.change_status') }}:</span>
                        <button v-for="s in statuses" :key="s" type="button"
                            :disabled="s === log.status"
                            class="rounded border border-slate-300 px-2 py-1 text-xs disabled:opacity-40 dark:border-slate-700"
                            @click="changeStatus(log, s)">
                            {{ t(`workshop.statuses.${s}`) }}
                        </button>
                    </div>
                </li>
            </ul>
        </section>
    </MechanicLayout>
</template>
