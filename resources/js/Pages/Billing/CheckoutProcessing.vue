<script setup>
// Landing page for Stripe's success redirect. Deliberately does NOT claim the
// subscription is active — activation happens via webhook (this URL can be
// visited without paying). Just reassures and points back to Billing, where
// the activated plan appears once the webhook lands.
import { computed } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import AppLayout from '@/Layouts/AppLayout.vue';
import Button from '@/Components/UI/Button.vue';

const { t } = useI18n();

const slug = computed(() => usePage().props.tenant?.slug ?? '');
</script>

<template>
    <AppLayout>
        <Head :title="t('billing.checkout_processing_title')" />

        <div class="mx-auto mt-16 max-w-md text-center">
            <div
                class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-success-50 dark:bg-success-900"
            >
                <svg
                    class="h-7 w-7 text-success-600 dark:text-success-100"
                    fill="none"
                    viewBox="0 0 24 24"
                    stroke-width="2"
                    stroke="currentColor"
                    aria-hidden="true"
                >
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                </svg>
            </div>

            <h1 class="mt-6 text-xl font-semibold text-ink-900 dark:text-ink-50">
                {{ t('billing.checkout_processing_title') }}
            </h1>
            <p class="mt-2 text-sm text-ink-500">
                {{ t('billing.checkout_processing_hint') }}
            </p>

            <div class="mt-8">
                <Link :href="`/app/${slug}/billing`">
                    <Button variant="primary">{{ t('billing.back_to_billing') }}</Button>
                </Link>
            </div>
        </div>
    </AppLayout>
</template>
