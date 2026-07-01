<script setup>
// Mechanic vehicle service page (reached from a QR scan). Shows the vehicle,
// its service history, a new-log form, and per-log status + parts controls.
// Design-system pass; mobile-first.
import { computed, reactive } from 'vue';
import { Head, useForm, router, usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import MechanicLayout from '@/Layouts/MechanicLayout.vue';
import StatusBadge from '@/Components/UI/StatusBadge.vue';
import Button from '@/Components/UI/Button.vue';
import Input from '@/Components/UI/Input.vue';
import Textarea from '@/Components/UI/Textarea.vue';
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

const statusVariants = {
    pending: 'neutral',
    in_progress: 'info',
    completed: 'success',
    waiting_for_parts: 'warning',
    re_inspection_required: 'danger',
};

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
        <div class="rounded-card border border-ink-200 bg-white p-4 shadow-subtle dark:border-ink-800 dark:bg-ink-900">
            <h1 class="text-2xl font-semibold tracking-tight text-ink-900 dark:text-ink-50">{{ vehicle.make }} {{ vehicle.model }}</h1>
            <p class="mt-1 text-sm text-ink-500">
                {{ vehicle.registration_number }} · {{ vehicle.year }} · {{ t(`fleet.statuses.${vehicle.status}`) }}
            </p>
        </div>

        <!-- New service log -->
        <section class="mt-6 rounded-card border border-ink-200 bg-white p-4 shadow-subtle dark:border-ink-800 dark:bg-ink-900">
            <h2 class="text-xs font-medium uppercase tracking-wide text-ink-500">{{ t('workshop.new_log') }}</h2>
            <form class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-2" @submit.prevent="createLog">
                <div class="sm:col-span-2">
                    <Input v-model="logForm.title" required :label="t('workshop.fields.title')" :error="logForm.errors.title" />
                </div>
                <div class="sm:col-span-2">
                    <Textarea v-model="logForm.description" :rows="2" :label="t('workshop.fields.description')" :error="logForm.errors.description" />
                </div>
                <Input v-model="logForm.odometer_reading" type="number" min="0" :label="t('workshop.fields.odometer_reading')" :error="logForm.errors.odometer_reading" />
                <Input v-model="logForm.labour_cost" type="number" min="0" step="0.01" :label="t('workshop.fields.labour_cost')" :error="logForm.errors.labour_cost" />
                <div class="sm:col-span-2">
                    <Button type="submit" :loading="logForm.processing">{{ t('workshop.new_log') }}</Button>
                </div>
            </form>
        </section>

        <!-- Service history -->
        <section class="mt-8">
            <h2 class="text-xs font-medium uppercase tracking-wide text-ink-500">{{ t('workshop.service_history') }}</h2>
            <p v-if="!serviceHistory.length" class="mt-3 text-sm text-ink-500">{{ t('workshop.no_history') }}</p>

            <ul v-else class="mt-3 space-y-4">
                <li v-for="log in serviceHistory" :key="log.id"
                    class="rounded-card border border-ink-200 bg-white p-4 shadow-subtle dark:border-ink-800 dark:bg-ink-900">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <p class="font-medium text-ink-900 dark:text-ink-50">{{ log.title }}</p>
                            <p v-if="log.description" class="mt-1 text-sm text-ink-500">{{ log.description }}</p>
                            <p class="mt-1 text-xs text-ink-400">{{ log.mechanic?.name }}</p>
                        </div>
                        <StatusBadge :variant="statusVariants[log.status]" :label="t(`workshop.statuses.${log.status}`)" />
                    </div>

                    <!-- Costs -->
                    <dl class="mt-3 flex flex-wrap gap-x-6 gap-y-1 text-sm">
                        <div><dt class="inline text-ink-500">{{ t('workshop.labour') }}:</dt>
                            <dd class="inline font-medium text-ink-900 dark:text-ink-50"> {{ formatAUD(log.labour_cost) }}</dd></div>
                        <div><dt class="inline text-ink-500">{{ t('workshop.total') }}:</dt>
                            <dd class="inline font-medium text-ink-900 dark:text-ink-50"> {{ formatAUD(log.total_cost) }}</dd></div>
                    </dl>

                    <!-- Parts -->
                    <div class="mt-3">
                        <p class="text-xs font-medium uppercase tracking-wide text-ink-400">{{ t('workshop.parts') }}</p>
                        <p v-if="!log.parts?.length" class="text-sm text-ink-500">{{ t('workshop.no_parts') }}</p>
                        <ul v-else class="mt-1 space-y-1 text-sm">
                            <li v-for="part in log.parts" :key="part.id" class="flex justify-between text-ink-700 dark:text-ink-200">
                                <span>{{ part.name }} × {{ part.quantity }}</span>
                                <span class="text-ink-500 tabular-nums">{{ formatAUD(part.total_cost) }}</span>
                            </li>
                        </ul>

                        <!-- Add part -->
                        <form class="mt-2 flex flex-wrap items-end gap-2" @submit.prevent="addPart(log)">
                            <Input v-model="partInput(log.id).name" required :label="t('workshop.fields.part_name')" />
                            <Input v-model="partInput(log.id).quantity" type="number" min="1" :label="t('workshop.fields.quantity')" class="w-20" />
                            <Input v-model="partInput(log.id).unit_cost" type="number" min="0" step="0.01" :label="t('workshop.fields.unit_cost')" class="w-28" />
                            <Button type="submit" variant="secondary">{{ t('workshop.add_part') }}</Button>
                        </form>
                    </div>

                    <!-- Status change -->
                    <div class="mt-3 flex flex-wrap items-center gap-2">
                        <span class="text-xs text-ink-500">{{ t('workshop.change_status') }}:</span>
                        <Button
                            v-for="s in statuses"
                            :key="s"
                            variant="secondary"
                            size="sm"
                            :disabled="s === log.status"
                            @click="changeStatus(log, s)"
                        >
                            {{ t(`workshop.statuses.${s}`) }}
                        </Button>
                    </div>
                </li>
            </ul>
        </section>
    </MechanicLayout>
</template>
