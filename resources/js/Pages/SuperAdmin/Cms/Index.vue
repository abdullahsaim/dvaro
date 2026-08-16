<script setup>
// Super admin landing-page CMS editor. Content blocks grouped by section into
// tabs. Text/richtext blocks edit inline (PUT per block); image blocks preview
// the current image and upload a replacement (POST per block). Each save busts
// the relevant cache server-side, so changes go live immediately.
// Design-system pass.
import { reactive, ref, computed } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import SuperAdminLayout from '@/Layouts/SuperAdminLayout.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import Textarea from '@/Components/UI/Textarea.vue';
import Button from '@/Components/UI/Button.vue';
import { cmsImage } from '@/cms/defaultImages.js';

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

        <PageHeader :title="t('superadmin.cms.title')" :description="t('superadmin.cms.subtitle')" />

        <p v-if="!sectionKeys.length" class="rounded-card border border-ink-200 p-6 text-sm text-ink-500 dark:border-ink-800">
            {{ t('superadmin.cms.empty') }}
        </p>

        <template v-else>
            <!-- Section tabs -->
            <div class="mb-6 flex flex-wrap gap-2 border-b border-ink-200 dark:border-ink-800">
                <button
                    v-for="section in sectionKeys"
                    :key="section"
                    type="button"
                    class="border-b-2 px-3 py-2 text-sm font-medium transition"
                    :class="activeTab === section
                        ? 'border-ink-950 text-ink-900 dark:border-ink-100 dark:text-ink-100'
                        : 'border-transparent text-ink-500 hover:text-ink-900 dark:hover:text-ink-200'"
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
                    class="rounded-card border border-ink-200 bg-white p-5 shadow-subtle dark:border-ink-800 dark:bg-ink-900"
                >
                    <p class="mb-2 font-mono text-xs text-ink-400">{{ block.key }}</p>

                    <!-- Image block -->
                    <template v-if="block.type === 'image'">
                        <div class="flex flex-col gap-4 sm:flex-row sm:items-start">
                            <div class="sm:w-64">
                                <p class="mb-1 text-xs font-medium text-ink-500">{{ t('superadmin.cms.current_image') }}</p>
                                <!-- Theme logos preview on a surface matching the theme they'll
                                     render on (white-on-transparent needs a dark bg and vice
                                     versa), regardless of the editor's own color mode. -->
                                <img
                                    v-if="cmsImage(block.key, block.image_url)"
                                    :src="cmsImage(block.key, block.image_url)"
                                    alt=""
                                    class="w-full rounded-control border object-contain p-2"
                                    :class="{
                                        'border-ink-700 bg-ink-950': block.key === 'logo_dark',
                                        'border-ink-200 bg-white': block.key === 'logo_light',
                                        'border-ink-200 bg-white dark:border-ink-700 dark:bg-ink-800': block.key !== 'logo_dark' && block.key !== 'logo_light',
                                    }"
                                />
                                <p v-else class="rounded-control border border-dashed border-ink-300 p-4 text-center text-xs text-ink-400 dark:border-ink-700">
                                    {{ t('superadmin.cms.no_image') }}
                                </p>
                                <p v-if="!block.image_url" class="mt-1 text-xs text-ink-400">{{ t('superadmin.cms.using_default') }}</p>
                            </div>
                            <div class="flex-1">
                                <label class="block text-sm font-medium text-ink-700 dark:text-ink-300">{{ t('superadmin.cms.upload_image') }}</label>
                                <input
                                    type="file"
                                    accept="image/png,image/jpeg,image/webp"
                                    :disabled="savingKey === block.key"
                                    class="mt-1 block w-full text-sm text-ink-600 file:mr-3 file:rounded-control file:border-0 file:bg-ink-950 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-white hover:file:bg-ink-800 dark:text-ink-300 dark:file:bg-ink-50 dark:file:text-ink-950"
                                    @change="uploadImage(block, $event)"
                                />
                                <p class="mt-2 text-xs text-ink-400">{{ t('superadmin.cms.image_hint') }}</p>
                                <p v-if="page.props.errors.image" class="mt-1 text-sm text-danger-600 dark:text-danger-500">{{ page.props.errors.image }}</p>
                            </div>
                        </div>
                    </template>

                    <!-- Text / richtext block -->
                    <template v-else>
                        <Textarea v-model="drafts[block.key]" :rows="block.type === 'richtext' ? 6 : 2" />
                        <div class="mt-3 flex items-center gap-3">
                            <Button :loading="savingKey === block.key" @click="saveText(block)">{{ t('superadmin.cms.save') }}</Button>
                        </div>
                    </template>
                </div>
            </div>
        </template>
    </SuperAdminLayout>
</template>
