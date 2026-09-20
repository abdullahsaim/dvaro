<script setup>
// Settings → Company profile. These details appear on invoices, agreements and
// emails. Admin writes; everyone may read (the form is disabled for others).
import { computed, ref } from 'vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { PhotoIcon } from '@heroicons/vue/24/outline';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import Button from '@/Components/UI/Button.vue';
import Input from '@/Components/UI/Input.vue';
import Select from '@/Components/UI/Select.vue';
import Textarea from '@/Components/UI/Textarea.vue';

const props = defineProps({
    company: { type: Object, required: true },
    states: { type: Array, required: true },
    canManage: { type: Boolean, default: false },
    logoMaxKb: { type: Number, default: 2048 },
});

const { t } = useI18n();
const page = usePage();
const base = computed(() => `/app/${page.props.tenant.slug}/settings/company`);

const form = useForm({
    name: props.company.name,
    legal_name: props.company.legal_name ?? '',
    abn: props.company.abn ?? '',
    phone: props.company.phone ?? '',
    email: props.company.email ?? '',
    website: props.company.website ?? '',
    address: props.company.address ?? '',
    brand_colour: props.company.brand_colour ?? '#0f172a',
    default_state: props.company.default_state ?? '',
});

function submit() {
    form.put(base.value, { preserveScroll: true });
}

// ── Logo ─────────────────────────────────────────────────────────────────────
const logoForm = useForm({ logo: null });
const fileInput = ref(null);

function pickLogo(event) {
    const file = event.target.files?.[0];
    event.target.value = '';
    if (!file) return;

    logoForm.logo = file;
    logoForm.post(`${base.value}/logo`, { forceFormData: true, preserveScroll: true });
}

function removeLogo() {
    router.delete(`${base.value}/logo`, { preserveScroll: true });
}
</script>

<template>
    <AppLayout>
        <Head :title="t('settings.company.title')" />

        <PageHeader :title="t('settings.company.title')" :description="t('settings.company.intro')">
            <template #actions>
                <Button variant="ghost" @click="router.visit(`/app/${page.props.tenant.slug}/settings`)">
                    {{ t('common.back') }}
                </Button>
            </template>
        </PageHeader>

        <div class="grid max-w-4xl grid-cols-1 gap-6 lg:grid-cols-3">
            <!-- Details -->
            <form class="space-y-4 lg:col-span-2" @submit.prevent="submit">
                <div class="rounded-card border border-ink-200 bg-white p-5 shadow-subtle dark:border-ink-800 dark:bg-ink-900">
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <Input v-model="form.name" required :disabled="!canManage" :label="t('settings.company.trading_name')" :error="form.errors.name" />
                        <Input v-model="form.legal_name" :disabled="!canManage" :label="t('settings.company.legal_name')" :error="form.errors.legal_name" />
                        <Input v-model="form.abn" :disabled="!canManage" :label="t('settings.company.abn')" placeholder="12 345 678 901" :error="form.errors.abn" />
                        <Select v-model="form.default_state" :disabled="!canManage" :label="t('settings.company.default_state')" :error="form.errors.default_state">
                            <option value="">{{ t('settings.company.no_state') }}</option>
                            <option v-for="s in states" :key="s" :value="s">{{ s }}</option>
                        </Select>
                        <Input v-model="form.phone" type="tel" :disabled="!canManage" :label="t('settings.company.phone')" :error="form.errors.phone" />
                        <Input v-model="form.email" type="email" :disabled="!canManage" :label="t('settings.company.email')" :error="form.errors.email" />
                        <div class="sm:col-span-2">
                            <Input v-model="form.website" type="url" :disabled="!canManage" :label="t('settings.company.website')" placeholder="https://" :error="form.errors.website" />
                        </div>
                        <div class="sm:col-span-2">
                            <Textarea v-model="form.address" :rows="3" :disabled="!canManage" :label="t('settings.company.address')" :error="form.errors.address" />
                        </div>
                    </div>
                </div>

                <div v-if="canManage" class="flex justify-end">
                    <Button type="submit" :loading="form.processing">{{ t('common.save') }}</Button>
                </div>
                <p v-else class="text-sm text-ink-500">{{ t('settings.admin_only') }}</p>
            </form>

            <!-- Branding -->
            <aside class="space-y-4">
                <div class="rounded-card border border-ink-200 bg-white p-5 shadow-subtle dark:border-ink-800 dark:bg-ink-900">
                    <h2 class="text-sm font-semibold text-ink-900 dark:text-ink-50">{{ t('settings.company.branding') }}</h2>
                    <p class="mt-1 text-sm text-ink-500">{{ t('settings.company.branding_hint') }}</p>

                    <div class="mt-4 flex h-28 items-center justify-center rounded-control border border-dashed border-ink-300 bg-ink-50 p-3 dark:border-ink-700 dark:bg-ink-950">
                        <img v-if="company.logo_url" :src="company.logo_url" :alt="company.name" class="max-h-20 max-w-full object-contain" />
                        <PhotoIcon v-else class="h-8 w-8 text-ink-300 dark:text-ink-600" />
                    </div>

                    <div v-if="canManage" class="mt-3 flex flex-wrap gap-2">
                        <Button variant="secondary" size="sm" :loading="logoForm.processing" @click="fileInput?.click()">
                            {{ company.logo_url ? t('settings.company.replace_logo') : t('settings.company.upload_logo') }}
                        </Button>
                        <Button v-if="company.logo_url" variant="ghost" size="sm" @click="removeLogo">{{ t('common.delete') }}</Button>
                        <input
                            ref="fileInput"
                            type="file"
                            accept=".png,.jpg,.jpeg,.svg,.webp,image/png,image/jpeg,image/svg+xml,image/webp"
                            class="hidden"
                            @change="pickLogo"
                        />
                    </div>
                    <p class="mt-2 text-xs text-ink-400">{{ t('settings.company.logo_hint', { mb: Math.round(logoMaxKb / 1024) }) }}</p>
                    <p v-if="logoForm.errors.logo" class="mt-1 text-sm text-danger-600 dark:text-danger-500">{{ logoForm.errors.logo }}</p>

                    <label class="mt-5 block text-sm font-medium text-ink-700 dark:text-ink-300">{{ t('settings.company.brand_colour') }}</label>
                    <div class="mt-1.5 flex items-center gap-2">
                        <input
                            v-model="form.brand_colour"
                            type="color"
                            :disabled="!canManage"
                            class="h-10 w-14 cursor-pointer rounded-control border border-ink-200 bg-white p-1 disabled:opacity-50 dark:border-ink-700 dark:bg-ink-900"
                            :aria-label="t('settings.company.brand_colour')"
                        />
                        <Input v-model="form.brand_colour" :disabled="!canManage" class="flex-1 font-mono" :error="form.errors.brand_colour" />
                    </div>
                    <p class="mt-1 text-xs text-ink-400">{{ t('settings.company.brand_colour_hint') }}</p>
                </div>
            </aside>
        </div>
    </AppLayout>
</template>
