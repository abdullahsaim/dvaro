<script setup>
// Temporary placeholder dashboard — proves the tenant session works end to end.
// Real dashboard arrives in a later session.
import { computed } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import AppLayout from '@/Layouts/AppLayout.vue';

const { t } = useI18n();
const page = usePage();

const user = computed(() => page.props.auth.user);
const logoutUrl = computed(() => `/app/${page.props.tenant.slug}/logout`);

function logout() {
    router.post(logoutUrl.value);
}
</script>

<template>
    <AppLayout>
        <Head title="Dashboard" />

        <div class="py-10">
            <h1 class="text-2xl font-semibold">
                {{ t('dashboard.welcome', { name: user.name }) }}
            </h1>

            <button
                type="button"
                class="mt-6 rounded bg-slate-800 px-3 py-2 text-white dark:bg-slate-200 dark:text-slate-900"
                @click="logout"
            >
                {{ t('auth.logout') }}
            </button>
        </div>
    </AppLayout>
</template>
