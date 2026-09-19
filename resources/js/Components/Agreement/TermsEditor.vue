<script setup>
// Rich-text editor for agreement terms (TipTap). Deliberately limited to the
// formatting the server allow-list keeps and dompdf renders reliably:
// paragraphs, H2/H3, bold, italic, underline, bullet + numbered lists.
//
// Merge fields ({{customer.name}} etc) are inserted as plain text from the
// picker; the server validates them on save and fills them in when an
// agreement is created. The HTML is sanitised server-side — this editor is a
// convenience, never the security boundary.
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { EditorContent, useEditor } from '@tiptap/vue-3';
import StarterKit from '@tiptap/starter-kit';
import Underline from '@tiptap/extension-underline';
import {
    BoldIcon,
    ItalicIcon,
    UnderlineIcon,
    ListBulletIcon,
    NumberedListIcon,
    ArrowUturnLeftIcon,
    ArrowUturnRightIcon,
    EyeIcon,
    PencilSquareIcon,
} from '@heroicons/vue/24/outline';

const props = defineProps({
    modelValue: { type: String, default: '' },
    mergeFields: { type: Object, default: () => ({}) }, // { 'customer.name': 'Customer full name' }
    sampleValues: { type: Object, default: () => ({}) },
    error: { type: String, default: '' },
});

const emit = defineEmits(['update:modelValue']);

const { t } = useI18n();

const editor = useEditor({
    content: props.modelValue,
    extensions: [
        StarterKit.configure({
            heading: { levels: [2, 3] },
            // Not in the server allow-list — keep the toolbar honest.
            codeBlock: false,
            blockquote: false,
            horizontalRule: false,
        }),
        Underline,
    ],
    editorProps: {
        attributes: {
            class: 'terms-content min-h-[22rem] px-4 py-3 focus:outline-none',
        },
    },
    onUpdate: ({ editor: e }) => emit('update:modelValue', e.getHTML()),
});

// Keep the editor in step when the parent swaps templates.
watch(() => props.modelValue, (value) => {
    if (editor.value && value !== editor.value.getHTML()) {
        editor.value.commands.setContent(value || '', false);
    }
});

onBeforeUnmount(() => editor.value?.destroy());

const showPreview = ref(false);

// Preview: fill the merge fields with sample values, escaping them exactly as
// the server does, so what you see matches the finished agreement.
function escapeHtml(value) {
    return String(value).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[c]));
}

const preview = computed(() =>
    (props.modelValue || '').replace(/\{\{\s*([a-z0-9_.]+)\s*\}\}/gi, (_, field) => {
        const value = props.sampleValues[field];
        return value ? escapeHtml(value) : '—';
    }),
);

const fieldList = computed(() => Object.entries(props.mergeFields).map(([key, label]) => ({ key, label })));

function insertField(key) {
    editor.value?.chain().focus().insertContent(`{{${key}}}`).run();
}

const buttons = computed(() => [
    { key: 'bold', icon: BoldIcon, action: () => editor.value?.chain().focus().toggleBold().run(), active: editor.value?.isActive('bold') },
    { key: 'italic', icon: ItalicIcon, action: () => editor.value?.chain().focus().toggleItalic().run(), active: editor.value?.isActive('italic') },
    { key: 'underline', icon: UnderlineIcon, action: () => editor.value?.chain().focus().toggleUnderline().run(), active: editor.value?.isActive('underline') },
    { key: 'bullet_list', icon: ListBulletIcon, action: () => editor.value?.chain().focus().toggleBulletList().run(), active: editor.value?.isActive('bulletList') },
    { key: 'ordered_list', icon: NumberedListIcon, action: () => editor.value?.chain().focus().toggleOrderedList().run(), active: editor.value?.isActive('orderedList') },
    { key: 'undo', icon: ArrowUturnLeftIcon, action: () => editor.value?.chain().focus().undo().run(), active: false },
    { key: 'redo', icon: ArrowUturnRightIcon, action: () => editor.value?.chain().focus().redo().run(), active: false },
]);

const toolbarButton = 'inline-flex h-8 w-8 items-center justify-center rounded-control transition-colors';
</script>

<template>
    <div>
        <div class="overflow-hidden rounded-card border border-ink-200 bg-white dark:border-ink-800 dark:bg-ink-900" :class="error ? 'border-danger-500' : ''">
            <!-- Toolbar -->
            <div class="flex flex-wrap items-center gap-1 border-b border-ink-200 bg-ink-50 px-2 py-1.5 dark:border-ink-800 dark:bg-ink-950">
                <button
                    v-for="level in [2, 3]"
                    :key="`h${level}`"
                    type="button"
                    class="h-8 rounded-control px-2 text-sm font-semibold transition-colors"
                    :class="editor?.isActive('heading', { level })
                        ? 'bg-ink-900 text-white dark:bg-ink-100 dark:text-ink-950'
                        : 'text-ink-600 hover:bg-ink-200 dark:text-ink-300 dark:hover:bg-ink-800'"
                    :aria-label="t(`agreement_template.editor.heading`, { level })"
                    @click="editor?.chain().focus().toggleHeading({ level }).run()"
                >
                    H{{ level }}
                </button>
                <span class="mx-1 h-5 w-px bg-ink-200 dark:bg-ink-800" aria-hidden="true" />
                <button
                    v-for="b in buttons"
                    :key="b.key"
                    type="button"
                    :class="[toolbarButton, b.active
                        ? 'bg-ink-900 text-white dark:bg-ink-100 dark:text-ink-950'
                        : 'text-ink-600 hover:bg-ink-200 dark:text-ink-300 dark:hover:bg-ink-800']"
                    :aria-label="t(`agreement_template.editor.${b.key}`)"
                    @click="b.action"
                >
                    <component :is="b.icon" class="h-4 w-4" />
                </button>

                <div class="ml-auto flex items-center gap-1">
                    <!-- Merge field picker -->
                    <select
                        class="h-8 max-w-[13rem] rounded-control border border-ink-200 bg-white px-2 text-xs text-ink-700 dark:border-ink-700 dark:bg-ink-900 dark:text-ink-200"
                        :aria-label="t('agreement_template.editor.insert_field')"
                        @change="insertField($event.target.value); $event.target.value = ''"
                    >
                        <option value="">{{ t('agreement_template.editor.insert_field') }}</option>
                        <option v-for="f in fieldList" :key="f.key" :value="f.key">{{ f.label }}</option>
                    </select>
                    <button
                        type="button"
                        :class="[toolbarButton, showPreview
                            ? 'bg-ink-900 text-white dark:bg-ink-100 dark:text-ink-950'
                            : 'text-ink-600 hover:bg-ink-200 dark:text-ink-300 dark:hover:bg-ink-800']"
                        :aria-label="t('agreement_template.editor.preview')"
                        :aria-pressed="showPreview"
                        @click="showPreview = !showPreview"
                    >
                        <PencilSquareIcon v-if="showPreview" class="h-4 w-4" />
                        <EyeIcon v-else class="h-4 w-4" />
                    </button>
                </div>
            </div>

            <!-- Editor / preview -->
            <div v-show="!showPreview">
                <EditorContent :editor="editor" />
            </div>
            <!-- eslint-disable-next-line vue/no-v-html -->
            <div
                v-if="showPreview"
                class="terms-content min-h-[22rem] px-4 py-3"
                v-html="preview"
            />
        </div>

        <p v-if="error" class="mt-1.5 text-sm text-danger-600 dark:text-danger-500">{{ error }}</p>
        <p v-else class="mt-1.5 text-xs text-ink-400">{{ t('agreement_template.editor.hint') }}</p>
    </div>
</template>
