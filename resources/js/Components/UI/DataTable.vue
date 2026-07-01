<script setup>
// Consistent table shell. The parent supplies header cells (#head) and body
// rows (default slot); empty + loading states are built in, and the footer
// renders an existing Laravel/Inertia paginator's links.
//
//   <DataTable :columns="5" :empty="items.length === 0" :pagination="invoices">
//     <template #head> <th ...>...</th> </template>
//     <tr v-for="row in items" ...> ... </tr>
//     <template #empty> <EmptyState ... /> </template>
//   </DataTable>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import Skeleton from './Skeleton.vue';

const props = defineProps({
    columns: { type: Number, default: 4 }, // for skeleton + empty colspan
    loading: { type: Boolean, default: false },
    empty: { type: Boolean, default: false },
    // A Laravel paginator object (has `links`, `from`, `to`, `total`). Optional.
    pagination: { type: Object, default: null },
});

const skeletonRows = 5;

// Show the footer only for a real, multi-page paginator (links includes the
// prev/next arrows, so > 3 entries means there is more than one page).
const showPagination = computed(
    () => Array.isArray(props.pagination?.links) && props.pagination.links.length > 3,
);
</script>

<template>
    <div class="overflow-hidden rounded-card border border-ink-200 bg-white shadow-subtle dark:border-ink-800 dark:bg-ink-900">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="border-b border-ink-200 bg-ink-50 text-left dark:border-ink-800 dark:bg-ink-900/60">
                    <tr class="text-xs font-medium uppercase tracking-wide text-ink-500">
                        <slot name="head" />
                    </tr>
                </thead>
                <tbody class="divide-y divide-ink-100 dark:divide-ink-800">
                    <!-- Loading -->
                    <template v-if="loading">
                        <tr v-for="r in skeletonRows" :key="`sk-${r}`">
                            <td v-for="c in columns" :key="`sk-${r}-${c}`" class="px-4 py-3">
                                <Skeleton class="h-4 w-full max-w-[8rem]" />
                            </td>
                        </tr>
                    </template>

                    <!-- Empty -->
                    <tr v-else-if="empty">
                        <td :colspan="columns" class="p-0">
                            <slot name="empty">
                                <div class="px-6 py-12 text-center text-sm text-ink-500">
                                    <slot name="empty-text">—</slot>
                                </div>
                            </slot>
                        </td>
                    </tr>

                    <!-- Rows -->
                    <slot v-else />
                </tbody>
            </table>
        </div>

        <!-- Pagination footer -->
        <div
            v-if="showPagination"
            class="flex flex-col items-center justify-between gap-3 border-t border-ink-200 px-4 py-3 text-sm dark:border-ink-800 sm:flex-row"
        >
            <p v-if="pagination.total !== undefined" class="text-ink-500">
                {{ pagination.from ?? 0 }}–{{ pagination.to ?? 0 }} / {{ pagination.total }}
            </p>
            <nav class="flex flex-wrap items-center gap-1">
                <template v-for="(link, i) in pagination.links" :key="i">
                    <span
                        v-if="!link.url"
                        class="inline-flex h-8 min-w-8 items-center justify-center rounded-control px-2 text-ink-300 dark:text-ink-600"
                        v-html="link.label"
                    />
                    <Link
                        v-else
                        :href="link.url"
                        preserve-scroll
                        class="inline-flex h-8 min-w-8 items-center justify-center rounded-control px-2 transition-colors"
                        :class="link.active
                            ? 'bg-ink-950 text-white dark:bg-ink-50 dark:text-ink-950'
                            : 'text-ink-600 hover:bg-ink-100 dark:text-ink-300 dark:hover:bg-ink-800'"
                        v-html="link.label"
                    />
                </template>
            </nav>
        </div>
    </div>
</template>
