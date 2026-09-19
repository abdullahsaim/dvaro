<script setup>
// Shared UI for managing agreement terms templates — used by the tenant page
// (Settings → Agreement templates) and the super-admin page (platform
// defaults). Lists templates, edits one at a time in the TipTap editor, and
// (tenant only) copies a read-only platform default into the company's own
// library before editing.
import { computed, ref } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { DocumentDuplicateIcon, PlusIcon } from '@heroicons/vue/24/outline';
import Button from '@/Components/UI/Button.vue';
import Input from '@/Components/UI/Input.vue';
import Select from '@/Components/UI/Select.vue';
import StatusBadge from '@/Components/UI/StatusBadge.vue';
import TermsEditor from '@/Components/Agreement/TermsEditor.vue';

const props = defineProps({
    templates: { type: Array, required: true },
    platformDefaults: { type: Array, default: () => [] }, // tenant page only
    types: { type: Array, required: true },
    states: { type: Array, required: true },
    mergeFields: { type: Object, required: true },
    sampleValues: { type: Object, required: true },
    canManage: { type: Boolean, default: false },
    // { store, update: '/…/:id', copy: '/…/:id/copy' }
    endpoints: { type: Object, required: true },
});

const { t } = useI18n();

const editing = ref(null); // template object, or 'new', or null

const form = useForm({
    name: '',
    agreement_type: '',
    state: '',
    body_html: '',
    is_active: true,
});

function startNew() {
    editing.value = 'new';
    form.reset();
    form.clearErrors();
}

function edit(template) {
    editing.value = template;
    form.clearErrors();
    form.name = template.name;
    form.agreement_type = template.agreement_type ?? '';
    form.state = template.state ?? '';
    form.body_html = template.body_html;
    form.is_active = template.is_active;
}

function cancel() {
    editing.value = null;
    form.reset();
}

function submit() {
    const options = { preserveScroll: true, onSuccess: () => cancel() };

    if (editing.value === 'new') {
        form.post(props.endpoints.store, options);
    } else {
        form.put(props.endpoints.update.replace(':id', editing.value.id), options);
    }
}

function copyDefault(template) {
    router.post(props.endpoints.copy.replace(':id', template.id), {}, { preserveScroll: true });
}

function toggleActive(template) {
    router.put(props.endpoints.update.replace(':id', template.id), {
        name: template.name,
        agreement_type: template.agreement_type,
        state: template.state,
        body_html: template.body_html,
        is_active: !template.is_active,
    }, { preserveScroll: true });
}

function scopeLabel(template) {
    const type = template.agreement_type ? t(`agreement.types.${template.agreement_type}`) : t('agreement_template.any_type');
    const state = template.state ?? t('agreement_template.all_states');
    return `${type} · ${state}`;
}

const editingTitle = computed(() =>
    editing.value === 'new' ? t('agreement_template.new') : t('agreement_template.editing', { name: editing.value?.name ?? '' }),
);

const card = 'rounded-card border border-ink-200 bg-white shadow-subtle dark:border-ink-800 dark:bg-ink-900';
</script>

<template>
    <div class="space-y-6">
        <!-- Editor -->
        <section v-if="editing" :class="card" class="p-5">
            <h2 class="text-sm font-semibold text-ink-900 dark:text-ink-50">{{ editingTitle }}</h2>

            <form class="mt-4 space-y-4" @submit.prevent="submit">
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                    <Input v-model="form.name" required :label="t('agreement_template.fields.name')" :error="form.errors.name" />
                    <Select v-model="form.agreement_type" :label="t('agreement_template.fields.type')" :error="form.errors.agreement_type">
                        <option value="">{{ t('agreement_template.any_type') }}</option>
                        <option v-for="type in types" :key="type" :value="type">{{ t(`agreement.types.${type}`) }}</option>
                    </Select>
                    <Select v-model="form.state" :label="t('agreement_template.fields.state')" :error="form.errors.state">
                        <option value="">{{ t('agreement_template.all_states') }}</option>
                        <option v-for="state in states" :key="state" :value="state">{{ state }}</option>
                    </Select>
                </div>

                <TermsEditor
                    v-model="form.body_html"
                    :merge-fields="mergeFields"
                    :sample-values="sampleValues"
                    :error="form.errors.body_html"
                />

                <div class="flex flex-wrap items-center justify-between gap-3">
                    <label class="flex items-center gap-2 text-sm text-ink-600 dark:text-ink-300">
                        <input
                            v-model="form.is_active"
                            type="checkbox"
                            class="h-4 w-4 rounded border-ink-300 accent-ink-900 dark:border-ink-700 dark:accent-ink-100"
                        />
                        {{ t('agreement_template.fields.active') }}
                    </label>
                    <div class="flex gap-2">
                        <Button variant="ghost" type="button" @click="cancel">{{ t('common.cancel') }}</Button>
                        <Button type="submit" :loading="form.processing">{{ t('common.save') }}</Button>
                    </div>
                </div>
            </form>
        </section>

        <!-- Own templates -->
        <section :class="card">
            <div class="flex flex-wrap items-center justify-between gap-2 border-b border-ink-200 p-4 dark:border-ink-800">
                <div>
                    <h2 class="text-sm font-semibold text-ink-900 dark:text-ink-50">{{ t('agreement_template.your_templates') }}</h2>
                    <p class="mt-0.5 text-sm text-ink-500">{{ t('agreement_template.your_templates_hint') }}</p>
                </div>
                <Button v-if="canManage && !editing" @click="startNew">
                    <PlusIcon class="h-4 w-4" />{{ t('agreement_template.new') }}
                </Button>
            </div>

            <p v-if="!templates.length" class="p-4 text-sm text-ink-500">{{ t('agreement_template.none') }}</p>
            <ul v-else class="divide-y divide-ink-100 dark:divide-ink-800">
                <li v-for="template in templates" :key="template.id" class="flex flex-wrap items-center justify-between gap-3 p-4">
                    <div class="min-w-0">
                        <p class="flex flex-wrap items-center gap-2 font-medium text-ink-900 dark:text-ink-50">
                            {{ template.name }}
                            <StatusBadge v-if="!template.is_active" variant="neutral" :label="t('agreement_template.archived')" />
                        </p>
                        <p class="mt-0.5 text-sm text-ink-500">
                            {{ scopeLabel(template) }} · {{ t('agreement_template.revision', { n: template.revision }) }}
                        </p>
                    </div>
                    <div v-if="canManage" class="flex items-center gap-3">
                        <button type="button" class="text-sm font-medium text-ink-900 hover:underline dark:text-ink-100" @click="edit(template)">
                            {{ t('common.edit') }}
                        </button>
                        <button type="button" class="text-sm text-ink-500 hover:underline" @click="toggleActive(template)">
                            {{ template.is_active ? t('agreement_template.archive') : t('agreement_template.restore') }}
                        </button>
                    </div>
                </li>
            </ul>
        </section>

        <!-- Platform defaults (tenant page only) -->
        <section v-if="platformDefaults.length" :class="card">
            <div class="border-b border-ink-200 p-4 dark:border-ink-800">
                <h2 class="text-sm font-semibold text-ink-900 dark:text-ink-50">{{ t('agreement_template.platform_defaults') }}</h2>
                <p class="mt-0.5 text-sm text-ink-500">{{ t('agreement_template.platform_defaults_hint') }}</p>
            </div>
            <ul class="divide-y divide-ink-100 dark:divide-ink-800">
                <li v-for="template in platformDefaults" :key="template.id" class="flex flex-wrap items-center justify-between gap-3 p-4">
                    <div class="min-w-0">
                        <p class="font-medium text-ink-900 dark:text-ink-50">{{ template.name }}</p>
                        <p class="mt-0.5 text-sm text-ink-500">{{ scopeLabel(template) }}</p>
                    </div>
                    <Button v-if="canManage" variant="secondary" size="sm" @click="copyDefault(template)">
                        <DocumentDuplicateIcon class="h-4 w-4" />{{ t('agreement_template.copy') }}
                    </Button>
                </li>
            </ul>
        </section>
    </div>
</template>
