<script setup>
// Settings → Staff. Invite people, change roles, deactivate and reactivate.
// Admin writes; other staff see a read-only list. The server refuses anything
// that would lock the company out (last admin, yourself), so the UI only
// disables the obvious cases — it is not the guard.
import { computed, ref } from 'vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { UserPlusIcon, PaperAirplaneIcon, XMarkIcon } from '@heroicons/vue/24/outline';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import Button from '@/Components/UI/Button.vue';
import Input from '@/Components/UI/Input.vue';
import Select from '@/Components/UI/Select.vue';
import Modal from '@/Components/UI/Modal.vue';
import StatusBadge from '@/Components/UI/StatusBadge.vue';
import DataTable from '@/Components/UI/DataTable.vue';
import { useTenantFormat } from '@/composables/useTenantFormat';

const props = defineProps({
    staff: { type: Array, required: true },
    invitations: { type: Array, default: () => [] },
    roles: { type: Array, required: true },
    currentUserId: { type: Number, default: null },
    canManage: { type: Boolean, default: false },
    usage: { type: Object, default: () => ({ current: 0, limit: -1 }) },
});

const { t } = useI18n();
const page = usePage();
const base = computed(() => `/app/${page.props.tenant.slug}/settings/staff`);

// The company's timezone and date format (Settings → Regional).
const { date, dateTime } = useTenantFormat();
const when = (v) => (v ? dateTime(v) : null);
const day = (v) => (v ? date(v) : null);

// Only one admin left? Then that admin can't be demoted or switched off.
const activeAdmins = computed(() => props.staff.filter((s) => s.is_active && s.role === 'tenant_admin').length);
function isLastAdmin(member) {
    return member.role === 'tenant_admin' && member.is_active && activeAdmins.value <= 1;
}
function isSelf(member) {
    return member.id === props.currentUserId;
}

const seatsLeft = computed(() => (props.usage.limit < 0 ? null : props.usage.limit - props.usage.current));

// ── Invite ───────────────────────────────────────────────────────────────────
const inviteOpen = ref(false);
const inviteForm = useForm({ name: '', email: '', role: 'tenant_staff' });

function openInvite() {
    inviteForm.reset();
    inviteForm.clearErrors();
    inviteOpen.value = true;
}

function sendInvite() {
    inviteForm.post(`${base.value}/invite`, {
        preserveScroll: true,
        onSuccess: () => (inviteOpen.value = false),
    });
}

// ── Row actions ──────────────────────────────────────────────────────────────
function changeRole(member, role) {
    if (role === member.role) return;
    router.put(`${base.value}/${member.id}`, { role }, { preserveScroll: true });
}

function setActive(member, isActive) {
    router.put(`${base.value}/${member.id}`, { is_active: isActive }, { preserveScroll: true });
}

function resend(invitation) {
    router.post(`${base.value}/invitations/${invitation.id}/resend`, {}, { preserveScroll: true });
}

function revoke(invitation) {
    router.delete(`${base.value}/invitations/${invitation.id}`, { preserveScroll: true });
}

const inviteStatusVariants = { pending: 'info', accepted: 'success', revoked: 'neutral', expired: 'warning' };
</script>

<template>
    <AppLayout>
        <Head :title="t('settings.staff.title')" />

        <PageHeader :title="t('settings.staff.title')" :description="t('settings.staff.intro')">
            <template #actions>
                <Button variant="ghost" @click="router.visit(`/app/${page.props.tenant.slug}/settings`)">
                    {{ t('common.back') }}
                </Button>
                <Button v-if="canManage" @click="openInvite">
                    <UserPlusIcon class="h-4 w-4" />{{ t('settings.staff.invite') }}
                </Button>
            </template>
        </PageHeader>

        <p v-if="seatsLeft !== null" class="mb-4 text-sm text-ink-500">
            {{ t('settings.staff.seats', { used: usage.current, limit: usage.limit }) }}
        </p>

        <!-- People -->
        <DataTable :columns="5" :empty="staff.length === 0">
            <template #head>
                <th class="px-4 py-3">{{ t('settings.staff.person') }}</th>
                <th class="px-4 py-3">{{ t('settings.staff.role') }}</th>
                <th class="px-4 py-3">{{ t('settings.staff.status') }}</th>
                <th class="px-4 py-3">{{ t('settings.staff.last_login') }}</th>
                <th class="px-4 py-3 text-right">{{ t('common.actions') }}</th>
            </template>

            <tr v-for="member in staff" :key="member.id" class="text-ink-700 dark:text-ink-200" :class="member.is_active ? '' : 'opacity-60'">
                <td class="px-4 py-3">
                    <p class="font-medium text-ink-900 dark:text-ink-50">
                        {{ member.name }}
                        <span v-if="isSelf(member)" class="ml-1 text-xs font-normal text-ink-400">{{ t('settings.staff.you') }}</span>
                    </p>
                    <p class="text-xs text-ink-500">{{ member.email }}</p>
                </td>
                <td class="px-4 py-3">
                    <Select
                        v-if="canManage && !isSelf(member) && !isLastAdmin(member)"
                        :model-value="member.role"
                        class="w-44"
                        :aria-label="t('settings.staff.role')"
                        @update:model-value="(role) => changeRole(member, role)"
                    >
                        <option v-for="role in roles" :key="role" :value="role">{{ t(`settings.staff.roles.${role}`) }}</option>
                    </Select>
                    <span v-else class="text-sm">{{ t(`settings.staff.roles.${member.role}`) }}</span>
                </td>
                <td class="px-4 py-3">
                    <StatusBadge
                        :variant="member.is_active ? 'success' : 'neutral'"
                        :label="member.is_active ? t('settings.staff.active') : t('settings.staff.inactive')"
                    />
                </td>
                <td class="px-4 py-3 text-sm tabular-nums">{{ when(member.last_login_at) ?? t('settings.staff.never') }}</td>
                <td class="px-4 py-3">
                    <div class="flex justify-end">
                        <button
                            v-if="canManage && !isSelf(member) && !isLastAdmin(member)"
                            type="button"
                            class="text-sm font-medium hover:underline"
                            :class="member.is_active ? 'text-danger-600 dark:text-danger-500' : 'text-ink-900 dark:text-ink-100'"
                            @click="setActive(member, !member.is_active)"
                        >
                            {{ member.is_active ? t('settings.staff.deactivate') : t('settings.staff.reactivate') }}
                        </button>
                        <span v-else-if="canManage && isLastAdmin(member)" class="text-xs text-ink-400">
                            {{ t('settings.staff.last_admin_note') }}
                        </span>
                    </div>
                </td>
            </tr>
        </DataTable>

        <!-- Invitations -->
        <section v-if="invitations.length" class="mt-8 max-w-4xl">
            <h2 class="text-xs font-medium uppercase tracking-wide text-ink-500">{{ t('settings.staff.invitations') }}</h2>
            <ul class="mt-3 divide-y divide-ink-100 rounded-card border border-ink-200 bg-white shadow-subtle dark:divide-ink-800 dark:border-ink-800 dark:bg-ink-900">
                <li v-for="invite in invitations" :key="invite.id" class="flex flex-wrap items-center justify-between gap-3 px-4 py-3">
                    <div class="min-w-0">
                        <p class="font-medium text-ink-900 dark:text-ink-50">{{ invite.name }}</p>
                        <p class="text-xs text-ink-500">
                            {{ invite.email }} · {{ t(`settings.staff.roles.${invite.role}`) }}
                            <template v-if="invite.status === 'pending'"> · {{ t('settings.staff.expires', { date: day(invite.expires_at) }) }}</template>
                        </p>
                    </div>
                    <div class="flex items-center gap-3">
                        <StatusBadge :variant="inviteStatusVariants[invite.status]" :label="t(`settings.staff.invite_status.${invite.status}`)" />
                        <template v-if="canManage && invite.status === 'pending'">
                            <button type="button" class="inline-flex items-center gap-1 text-sm text-ink-700 hover:underline dark:text-ink-200" @click="resend(invite)">
                                <PaperAirplaneIcon class="h-4 w-4" />{{ t('settings.staff.resend') }}
                            </button>
                            <button type="button" class="inline-flex items-center gap-1 text-sm text-danger-600 hover:underline dark:text-danger-500" @click="revoke(invite)">
                                <XMarkIcon class="h-4 w-4" />{{ t('settings.staff.revoke') }}
                            </button>
                        </template>
                    </div>
                </li>
            </ul>
        </section>

        <!-- Invite dialog -->
        <Modal :show="inviteOpen" :title="t('settings.staff.invite')" @close="inviteOpen = false">
            <form id="invite-staff" class="space-y-4" @submit.prevent="sendInvite">
                <p class="text-sm text-ink-500">{{ t('settings.staff.invite_hint') }}</p>
                <Input v-model="inviteForm.name" required :label="t('settings.staff.name')" :error="inviteForm.errors.name" />
                <Input v-model="inviteForm.email" type="email" required :label="t('settings.staff.email')" :error="inviteForm.errors.email" />
                <Select v-model="inviteForm.role" :label="t('settings.staff.role')" :error="inviteForm.errors.role">
                    <option v-for="role in roles" :key="role" :value="role">{{ t(`settings.staff.roles.${role}`) }}</option>
                </Select>
                <p class="text-xs text-ink-500">{{ t(`settings.staff.role_hints.${inviteForm.role}`) }}</p>
                <p v-if="inviteForm.errors.plan_limit" class="text-sm text-warning-700 dark:text-warning-500">{{ inviteForm.errors.plan_limit }}</p>
            </form>
            <template #footer>
                <Button variant="ghost" @click="inviteOpen = false">{{ t('common.cancel') }}</Button>
                <Button type="submit" form="invite-staff" :loading="inviteForm.processing">{{ t('settings.staff.send_invite') }}</Button>
            </template>
        </Modal>
    </AppLayout>
</template>
