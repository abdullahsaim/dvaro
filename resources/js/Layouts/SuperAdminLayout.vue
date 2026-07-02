<script setup>
// Super admin panel shell. Same sidebar/topbar pattern as AppLayout but with a
// forced-dark monochrome sidebar rail + a "Platform" context label, so a super
// admin can never confuse this with a tenant app. Platform-wide — no slug.
// DELIBERATELY separate from the tenant/customer/mechanic apps + guard.
import { computed, onMounted } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import {
    HomeIcon,
    BuildingOffice2Icon,
    Squares2X2Icon,
    CreditCardIcon,
    GlobeAltIcon,
    InboxArrowDownIcon,
    ArrowUpCircleIcon,
    Cog6ToothIcon,
} from '@heroicons/vue/24/outline';
import Sidebar from '@/Components/UI/Sidebar.vue';
import TopBar from '@/Components/UI/TopBar.vue';
import Toast from '@/Components/UI/Toast.vue';
import { useColorMode } from '@/composables/useColorMode';

const { t } = useI18n();
const page = usePage();

const admin = computed(() => page.props.auth?.superAdmin ?? null);
const currentPath = computed(() => (page.url || '').split('?')[0]);

function isActive(href, exact = false) {
    const p = currentPath.value;
    return exact ? p === href : p === href || p.startsWith(`${href}/`);
}

const navItems = computed(() =>
    [
        { key: 'dashboard', label: t('superadmin.nav.dashboard'), href: '/superadmin/dashboard', icon: HomeIcon, exact: true },
        { key: 'tenants', label: t('superadmin.nav.tenants'), href: '/superadmin/tenants', icon: BuildingOffice2Icon },
        { key: 'plans', label: t('superadmin.nav.plans'), href: '/superadmin/plans', icon: Squares2X2Icon },
        { key: 'subscriptions', label: t('superadmin.nav.subscriptions'), href: '/superadmin/subscriptions', icon: CreditCardIcon },
        { key: 'cms', label: t('superadmin.nav.cms'), href: '/superadmin/cms', icon: GlobeAltIcon },
        { key: 'demo_requests', label: t('superadmin.nav.demo_requests'), href: '/superadmin/demo-requests', icon: InboxArrowDownIcon },
        { key: 'upgrade_requests', label: t('superadmin.nav.upgrade_requests'), href: '/superadmin/upgrade-requests', icon: ArrowUpCircleIcon },
        { key: 'settings', label: t('superadmin.nav.settings'), href: '/superadmin/settings', icon: Cog6ToothIcon },
    ].map((item) => ({ ...item, active: isActive(item.href, item.exact) })),
);

const { syncFromServer } = useColorMode();
onMounted(syncFromServer);
</script>

<template>
    <div class="flex min-h-screen flex-col bg-ink-50 text-ink-900 dark:bg-ink-950 dark:text-ink-100">
        <div class="flex min-h-0 flex-1">
            <Sidebar :items="navItems" title="DVARO" :context-label="t('superadmin.panel')" variant="dark" />

            <div class="flex min-w-0 flex-1 flex-col">
                <TopBar :title="t('superadmin.panel')" :user="admin" logout-href="/superadmin/logout" />

                <main class="flex-1 overflow-y-auto">
                    <div class="mx-auto w-full max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
                        <slot />
                    </div>
                </main>
            </div>
        </div>

        <Toast />
    </div>
</template>
