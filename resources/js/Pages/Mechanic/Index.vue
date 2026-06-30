<script setup>
// Mechanic accounts list (tenant admin). FUNCTIONAL ONLY — design pass later.
import { computed } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    mechanics: { type: Object, required: true }, // Laravel paginator payload
});

const { t } = useI18n();
const page = usePage();

const slug = computed(() => page.props.tenant.slug);
const base = computed(() => `/app/${slug.value}/mechanics`);
const flash = computed(() => page.props.flash?.success);

function destroy(mechanic) {
    if (!window.confirm(t('mechanic.confirm_delete'))) return;
    router.delete(`${base.value}/${mechanic.id}`, { preserveScroll: true });
}
</script>

<template>
    <AppLayout>
        <Head :title="t('mechanic.title')" />

        <div class="py-10">
            <div class="flex items-center justify-between">
                <h1 class="text-2xl font-semibold">{{ t('mechanic.title') }}</h1>
                <Link
                    :href="`${base}/create`"
                    class="rounded bg-slate-800 px-3 py-2 text-sm text-white dark:bg-slate-200 dark:text-slate-900"
                >
                    {{ t('mechanic.add_mechanic') }}
                </Link>
            </div>

            <p
                v-if="flash"
                class="mt-4 rounded border border-green-200 bg-green-50 px-3 py-2 text-sm text-green-800 dark:border-green-900 dark:bg-green-900/30 dark:text-green-300"
            >
                {{ flash }}
            </p>

            <div class="mt-6 overflow-x-auto rounded border border-slate-200 dark:border-slate-800">
                <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-800">
                    <thead class="text-left text-slate-500 dark:text-slate-400">
                        <tr>
                            <th class="px-4 py-3 font-medium">{{ t('mechanic.fields.name') }}</th>
                            <th class="px-4 py-3 font-medium">{{ t('mechanic.fields.email') }}</th>
                            <th class="px-4 py-3 font-medium">{{ t('mechanic.fields.phone') }}</th>
                            <th class="px-4 py-3 font-medium">{{ t('mechanic.fields.is_active') }}</th>
                            <th class="px-4 py-3 text-right font-medium">{{ t('common.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        <tr v-if="mechanics.data.length === 0">
                            <td colspan="5" class="px-4 py-8 text-center text-slate-500 dark:text-slate-400">
                                {{ t('mechanic.empty') }}
                            </td>
                        </tr>
                        <tr v-for="mechanic in mechanics.data" :key="mechanic.id">
                            <td class="px-4 py-3 font-medium">{{ mechanic.name }}</td>
                            <td class="px-4 py-3">{{ mechanic.email }}</td>
                            <td class="px-4 py-3">{{ mechanic.phone ?? '—' }}</td>
                            <td class="px-4 py-3">
                                <span
                                    class="rounded-full px-2 py-0.5 text-xs"
                                    :class="mechanic.is_active
                                        ? 'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300'
                                        : 'bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400'"
                                >
                                    {{ mechanic.is_active ? t('mechanic.active_badge') : t('mechanic.inactive_badge') }}
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex justify-end gap-3">
                                    <Link :href="`${base}/${mechanic.id}/edit`" class="text-slate-600 hover:underline dark:text-slate-300">
                                        {{ t('common.edit') }}
                                    </Link>
                                    <button type="button" class="text-red-600 hover:underline dark:text-red-400" @click="destroy(mechanic)">
                                        {{ t('common.delete') }}
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div v-if="mechanics.links.length > 3" class="mt-4 flex flex-wrap gap-1">
                <component
                    :is="link.url ? Link : 'span'"
                    v-for="(link, i) in mechanics.links"
                    :key="i"
                    :href="link.url"
                    class="rounded px-3 py-1 text-sm"
                    :class="[
                        link.active ? 'bg-slate-800 text-white dark:bg-slate-200 dark:text-slate-900' : 'text-slate-600 dark:text-slate-300',
                        !link.url && 'cursor-default opacity-40',
                    ]"
                    v-html="link.label"
                />
            </div>
        </div>
    </AppLayout>
</template>
