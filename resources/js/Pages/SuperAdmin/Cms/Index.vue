<script setup>
// Super admin landing-page CMS editor. Content blocks grouped by section into
// tabs. Text/richtext blocks edit inline (PUT per block); image blocks preview
// the current image and upload a replacement (POST per block). Each save busts
// the relevant cache server-side, so changes go live immediately.
// FUNCTIONAL ONLY — design pass later.
import { reactive, ref, computed } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import SuperAdminLayout from '@/Layouts/SuperAdminLayout.vue';

const props = defineProps({
    // { section: [ { key, type, section, sort_order, content, image_url } ] }
    sections: { type: Object, default: () => ({}) },
});

const { t } = useI18n();
const page = usePage();

const sectionKeys = computed(() => Object.keys(props.sections));
const activeTab = ref(sectionKeys.value[0] ?? null);

// Local editable copy of each text/richtext block's content, keyed by block key.
const drafts = reactive({});
for (const blocks of Object.values(props.sections)) {
    for (const block of blocks) {
        if (block.type !== 'image') {
            drafts[block.key] = block.content ?? '';
        }
    }
}

const savingKey = ref(null);
const flash = computed(() => page.props.flash?.success ?? null);

function sectionLabel(section) {
    const key = `superadmin.cms.sections.${section}`;
    const label = t(key);
    return label === key ? section : label;
}

function saveText(block) {
    savingKey.value = block.key;
    router.put(`/superadmin/cms/${block.key}`, { content: drafts[block.key] }, {
        preserveScroll: true,
        preserveState: true,
        onFinish: () => (savingKey.value = null),
    });
}

function uploadImage(block, event) {
    const file = event.target.files?.[0];
    if (!file) return;
    savingKey.value = block.key;
    router.post(`/superadmin/cms/${block.key}/image`, { image: file }, {
        forceFormData: true,
        preserveScroll: true,
        onFinish: () => {
            savingKey.value = null;
            event.target.value = '';
        },
    });
}
</script>

<template>
    <SuperAdminLayout>
        <Head :title="t('superadmin.cms.title')" />

        <div class="py-8">
            <div class="mb-6">
                <h1 class="text-2xl font-semibold tracking-tight">{{ t('superadmin.cms.title') }}</h1>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ t('superadmin.cms.subtitle') }}</p>
            </div>

            <p v-if="flash" class="mb-4 rounded-lg bg-emerald-50 px-4 py-3 text-sm text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300">
                {{ flash }}
            </p>

            <p v-if="!sectionKeys.length" class="rounded-lg border border-slate-200 p-6 text-sm text-slate-500 dark:border-slate-800 dark:text-slate-400">
                {{ t('superadmin.cms.empty') }}
            </p>

            <template v-else>
                <!-- Section tabs -->
                <div class="mb-6 flex flex-wrap gap-2 border-b border-slate-200 dark:border-slate-800">
                    <button
                        v-for="section in sectionKeys"
                        :key="section"
                        type="button"
                        class="border-b-2 px-3 py-2 text-sm font-medium transition"
                        :class="activeTab === section
                            ? 'border-indigo-600 text-indigo-600 dark:text-indigo-400'
                            : 'border-transparent text-slate-500 hover:text-slate-800 dark:text-slate-400 dark:hover:text-slate-200'"
                        @click="activeTab = section"
                    >
                        {{ sectionLabel(section) }}
                    </button>
                </div>

                <!-- Blocks for the active section -->
                <div class="space-y-5">
                    <div
                        v-for="block in sections[activeTab]"
                        :key="block.key"
                        class="rounded-xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900"
                    >
                        <p class="mb-2 font-mono text-xs text-slate-400">{{ block.key }}</p>

                        <!-- Image block -->
                        <template v-if="block.type === 'image'">
                            <div class="flex flex-col gap-4 sm:flex-row sm:items-start">
                                <div class="sm:w-64">
                                    <p class="mb-1 text-xs font-medium text-slate-500 dark:text-slate-400">{{ t('superadmin.cms.current_image') }}</p>
                                    <img v-if="block.image_url" :src="block.image_url" alt="" class="w-full rounded-lg border border-slate-200 object-cover dark:border-slate-700" />
                                    <p v-else class="rounded-lg border border-dashed border-slate-300 p-4 text-center text-xs text-slate-400 dark:border-slate-700">
                                        {{ t('superadmin.cms.no_image') }}
                                    </p>
                                </div>
                                <div class="flex-1">
                                    <label class="block text-sm font-medium">{{ t('superadmin.cms.upload_image') }}</label>
                                    <input
                                        type="file"
                                        accept="image/png,image/jpeg,image/webp"
                                        :disabled="savingKey === block.key"
                                        class="mt-1 block w-full text-sm text-slate-600 file:mr-3 file:rounded-lg file:border-0 file:bg-indigo-600 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-white hover:file:bg-indigo-500 dark:text-slate-300"
                                        @change="uploadImage(block, $event)"
                                    />
                                    <p class="mt-2 text-xs text-slate-400">{{ t('superadmin.cms.image_hint') }}</p>
                                    <p v-if="page.props.errors.image" class="mt-1 text-sm text-red-600">{{ page.props.errors.image }}</p>
                                </div>
                            </div>
                        </template>

                        <!-- Text / richtext block -->
                        <template v-else>
                            <textarea
                                v-model="drafts[block.key]"
                                :rows="block.type === 'richtext' ? 6 : 2"
                                class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-950"
                            ></textarea>
                            <div class="mt-3 flex items-center gap-3">
                                <button
                                    type="button"
                                    :disabled="savingKey === block.key"
                                    class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-indigo-500 disabled:opacity-50"
                                    @click="saveText(block)"
                                >
                                    {{ t('superadmin.cms.save') }}
                                </button>
                            </div>
                        </template>
                    </div>
                </div>
            </template>
        </div>
    </SuperAdminLayout>
</template>
