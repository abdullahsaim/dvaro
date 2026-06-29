<script setup>
// Customer portal shell. Wraps authenticated /portal/{tenant_slug}/ pages.
// FUNCTIONAL ONLY — design pass later. Uses the customer-guard auth payload
// shared by ResolveTenantForCustomer (auth.customer), never the tenant guard.
// Deliberately separate from AppLayout / SuperAdminLayout / MechanicLayout.
import { computed } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();
const page = usePage();

const slug = computed(() => page.props.tenant?.slug);
const tenantName = computed(() => page.props.tenant?.name);
const customer = computed(() => page.props.auth?.customer ?? null);
const flashSuccess = computed(() => page.props.flash?.success);
const flashError = computed(() => page.props.flash?.error);
const base = computed(() => `/portal/${slug.value}`);

const nav = computed(() => [
    { label: t('customer.portal.dashboard'), href: `${base.value}/dashboard` },
    { label: t('customer.portal.invoices'), href: `${base.value}/invoices` },
    { label: t('customer.portal.agreements'), href: `${base.value}/agreements` },
]);

function logout() {
    router.post(`${base.value}/logout`);
}
</script>

<template>
    <div class="min-h-screen bg-white text-slate-900 transition-colors dark:bg-slate-950 dark:text-slate-100">
        <header class="border-b border-slate-200 dark:border-slate-800">
            <div class="mx-auto flex w-full max-w-5xl items-center justify-between px-4 py-3 sm:px-6 lg:px-8">
                <div class="flex items-center gap-6">
                    <Link :href="`${base}/dashboard`" class="text-lg font-semibold">
                        {{ tenantName }}
                    </Link>
                    <nav v-if="customer" class="hidden items-center gap-4 text-sm sm:flex">
                        <Link
                            v-for="item in nav"
                            :key="item.href"
                            :href="item.href"
                            class="text-slate-600 hover:text-slate-900 dark:text-slate-300 dark:hover:text-white"
                        >
                            {{ item.label }}
                        </Link>
                    </nav>
                </div>
                <div v-if="customer" class="flex items-center gap-4 text-sm">
                    <span class="hidden text-slate-500 dark:text-slate-400 sm:inline">{{ customer.email }}</span>
                    <button type="button" class="text-slate-600 hover:underline dark:text-slate-300" @click="logout">
                        {{ t('auth.logout') }}
                    </button>
                </div>
            </div>
            <!-- Mobile nav -->
            <nav v-if="customer" class="flex items-center gap-4 border-t border-slate-200 px-4 py-2 text-sm sm:hidden dark:border-slate-800">
                <Link v-for="item in nav" :key="item.href" :href="item.href" class="text-slate-600 dark:text-slate-300">
                    {{ item.label }}
                </Link>
            </nav>
        </header>

        <main class="mx-auto w-full max-w-5xl px-4 py-8 sm:px-6 lg:px-8">
            <p
                v-if="flashSuccess"
                class="mb-6 rounded border border-green-200 bg-green-50 px-3 py-2 text-sm text-green-800 dark:border-green-900 dark:bg-green-900/30 dark:text-green-300"
            >
                {{ flashSuccess }}
            </p>
            <p
                v-if="flashError"
                class="mb-6 rounded border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-800 dark:border-red-900 dark:bg-red-900/30 dark:text-red-300"
            >
                {{ flashError }}
            </p>
            <slot />
        </main>
    </div>
</template>
