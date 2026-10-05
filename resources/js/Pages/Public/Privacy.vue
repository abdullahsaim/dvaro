<script setup>
// Static legal page — NOT CMS-driven (see PublicLandingController::privacy()).
// Draft content; replace with lawyer-reviewed wording before relying on it,
// same caveat as the seeded agreement-terms templates (Session 33).
import { Head } from '@inertiajs/vue3';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import { vReveal } from '@/directives/reveal.js';
import { useSeo } from '@/composables/useSeo.js';

const seo = useSeo({
    title: 'Privacy Policy',
    description: 'How DVARO collects, uses and protects your data.',
});

const updated = 'October 2026';
</script>

<template>
    <PublicLayout>
        <Head :title="seo.title">
            <link rel="canonical" head-key="canonical" :href="seo.canonical" />
            <meta v-for="m in seo.meta" :key="m.key" :head-key="m.key" :name="m.name" :property="m.property" :content="m.content" />
        </Head>

        <section class="border-b border-ink-200 dark:border-ink-800">
            <div v-reveal class="mx-auto w-full max-w-3xl px-4 py-16 sm:px-6 lg:px-8">
                <p class="text-xs font-semibold uppercase tracking-widest text-ink-400">Legal</p>
                <h1 class="mt-3 text-4xl font-bold tracking-tight text-ink-900 dark:text-ink-50">Privacy Policy</h1>
                <p class="mt-3 text-sm text-ink-500">Last updated {{ updated }}</p>
            </div>
        </section>

        <section class="mx-auto w-full max-w-3xl px-4 py-16 sm:px-6 lg:px-8">
            <div class="prose prose-ink max-w-none text-ink-700 dark:prose-invert dark:text-ink-300">
                <p>
                    DVARO is operated by DJ Group of Companies Pty Ltd ("DVARO", "we", "us"), based in Canning
                    Vale, Western Australia. This policy explains what personal information we collect through
                    the DVARO platform, how we use it, and the choices you have.
                </p>

                <h2>1. Who this policy covers</h2>
                <p>
                    DVARO is a multi-tenant platform used by car rental companies ("tenants") to run their
                    business. This policy covers information we collect directly (e.g. when a company signs up
                    for DVARO) and information a tenant's own customers provide through that tenant's workspace
                    (e.g. a rental agreement, a customer portal account). Each tenant is responsible for their
                    own customers' data as the data controller; DVARO acts as the processor on their behalf.
                </p>

                <h2>2. Information we collect</h2>
                <ul>
                    <li><strong>Account information</strong> — name, work email, password, and company details when a tenant registers.</li>
                    <li><strong>Customer and rental records</strong> — entered by a tenant into their own workspace: names, contact details, driver's licence or passport numbers (encrypted at rest), rental history, and agreement/invoice records.</li>
                    <li><strong>Payment information</strong> — subscription and invoice payments are processed by Stripe and PayPal; DVARO does not store full card numbers.</li>
                    <li><strong>Usage data</strong> — log data, device/browser information, and activity within the platform, used for security auditing and service improvement.</li>
                    <li><strong>Communications</strong> — messages sent via the contact form, demo requests, or support correspondence.</li>
                </ul>

                <h2>3. How we use information</h2>
                <ul>
                    <li>To provide and operate the DVARO platform and each tenant's workspace.</li>
                    <li>To process subscription billing and send related notifications.</li>
                    <li>To send transactional notifications (e.g. registration/insurance expiry, invoice reminders) via email, SMS or WhatsApp, through the provider each tenant selects.</li>
                    <li>To maintain security, detect abuse, and keep an audit trail of account and billing changes.</li>
                    <li>To respond to support, contact-form and demo-request enquiries.</li>
                </ul>

                <h2>4. Data isolation between tenants</h2>
                <p>
                    Every tenant's data is isolated at the database level — one tenant can never see another
                    tenant's customers, pricing, agreements or financial records. Sensitive fields such as
                    licence and passport numbers are encrypted at rest.
                </p>

                <h2>5. Third parties we share data with</h2>
                <p>
                    We use trusted service providers to operate DVARO, including payment processors (Stripe,
                    PayPal), email/SMS/WhatsApp delivery providers (as selected per tenant), and our hosting
                    provider. These providers only receive the information needed to perform their function and
                    are not permitted to use it for their own purposes.
                </p>

                <h2>6. Data retention</h2>
                <p>
                    We retain account and financial records for as long as a subscription is active and for a
                    reasonable period afterward to meet legal, accounting and dispute-resolution obligations.
                    Ledger and agreement records are kept append-only and are not deleted, consistent with
                    financial record-keeping requirements.
                </p>

                <h2>7. Your rights</h2>
                <p>
                    Subject to applicable law (including the Australian Privacy Act 1988), you may request
                    access to, correction of, or deletion of your personal information. Where your data was
                    entered by a rental company you dealt with, please contact that company first — they control
                    their own customer records. For anything else, contact us using the details below.
                </p>

                <h2>8. Cookies</h2>
                <p>
                    DVARO uses essential cookies to keep you signed in and remember your display preferences
                    (such as light/dark mode). We do not use third-party advertising trackers.
                </p>

                <h2>9. Changes to this policy</h2>
                <p>
                    We may update this policy from time to time. Material changes will be reflected by updating
                    the "last updated" date above.
                </p>

                <h2>10. Contact us</h2>
                <p>
                    Questions about this policy can be sent to
                    <a href="mailto:hello@dvaro.com.au">hello@dvaro.com.au</a>.
                </p>

                <p class="text-sm text-ink-400">
                    This page is a general-purpose draft and has not yet been reviewed by a lawyer. It should be
                    reviewed and finalised before being relied upon as DVARO's complete privacy policy.
                </p>
            </div>
        </section>
    </PublicLayout>
</template>
