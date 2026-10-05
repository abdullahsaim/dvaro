<script setup>
// Static legal page — NOT CMS-driven (see PublicLandingController::terms()).
// Draft content; replace with lawyer-reviewed wording before relying on it,
// same caveat as the seeded agreement-terms templates (Session 33).
import { Head } from '@inertiajs/vue3';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import { vReveal } from '@/directives/reveal.js';
import { useSeo } from '@/composables/useSeo.js';

const seo = useSeo({
    title: 'Terms of Service',
    description: 'The terms that govern use of the DVARO platform.',
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
                <h1 class="mt-3 text-4xl font-bold tracking-tight text-ink-900 dark:text-ink-50">Terms of Service</h1>
                <p class="mt-3 text-sm text-ink-500">Last updated {{ updated }}</p>
            </div>
        </section>

        <section class="mx-auto w-full max-w-3xl px-4 py-16 sm:px-6 lg:px-8">
            <div class="prose prose-ink max-w-none text-ink-700 dark:prose-invert dark:text-ink-300">
                <p>
                    These Terms of Service ("Terms") govern access to and use of the DVARO platform, operated by
                    DJ Group of Companies Pty Ltd ("DVARO", "we", "us"), based in Canning Vale, Western
                    Australia. By registering a company or using DVARO, you agree to these Terms.
                </p>

                <h2>1. The service</h2>
                <p>
                    DVARO is a software-as-a-service platform for car rental businesses, covering fleet
                    management, rental agreements, invoicing, workshop management, and related tools. Features
                    available to your company depend on your selected subscription plan.
                </p>

                <h2>2. Accounts and registration</h2>
                <p>
                    You must provide accurate information when registering and keep your login credentials
                    confidential. You are responsible for all activity under your company's account, including
                    actions taken by staff you invite.
                </p>

                <h2>3. Subscriptions and billing</h2>
                <ul>
                    <li>Paid plans are billed monthly or annually as selected at signup, via Stripe or PayPal.</li>
                    <li>A free trial, where offered, converts to a paid subscription only if you choose a paid plan — no card is required to start a trial.</li>
                    <li>Plan limits (vehicles, staff, storage, etc.) are enforced as hard limits; exceeding a limit requires an upgrade.</li>
                    <li>You may cancel at any time from your billing page. Cancellation takes effect at the end of the current billing period; no partial refunds are provided for the remainder of a paid period except where required by law.</li>
                    <li>Fees are quoted in Australian Dollars (AUD) and are exclusive of GST unless stated otherwise.</li>
                </ul>

                <h2>4. Your data and content</h2>
                <p>
                    You retain ownership of the data you enter into DVARO — your fleet, customers, agreements
                    and financial records. We do not sell your data and only access it to operate the platform,
                    provide support, or as required by law. See our
                    <a href="/privacy">Privacy Policy</a> for details on how data is handled.
                </p>

                <h2>5. Acceptable use</h2>
                <p>You agree not to:</p>
                <ul>
                    <li>Use DVARO for any unlawful purpose, or to store or process data you do not have the right to hold.</li>
                    <li>Attempt to access another tenant's data or circumvent the platform's tenant isolation or security controls.</li>
                    <li>Interfere with or disrupt the platform's operation, including through excessive automated requests.</li>
                    <li>Reverse-engineer, resell, or white-label the platform without our written consent.</li>
                </ul>

                <h2>6. Agreements signed through DVARO</h2>
                <p>
                    Rental agreements, consent forms and related documents generated and signed through DVARO
                    are created and stored by your company for your own rental transactions. You are responsible
                    for ensuring the content of your agreement templates complies with the laws applicable to
                    your business.
                </p>

                <h2>7. Service availability</h2>
                <p>
                    We aim to keep DVARO available and performant but do not guarantee uninterrupted access.
                    Scheduled maintenance will be communicated where practical. We are not liable for
                    unavailability caused by factors outside our reasonable control.
                </p>

                <h2>8. Termination</h2>
                <p>
                    We may suspend or terminate an account that breaches these Terms, engages in fraudulent
                    activity, or fails to pay subscription fees after reasonable notice. You may close your
                    account at any time; some records (e.g. financial ledgers) are retained after closure as
                    required by law.
                </p>

                <h2>9. Limitation of liability</h2>
                <p>
                    To the maximum extent permitted by law, DVARO's liability for any claim arising from your
                    use of the platform is limited to the fees you paid in the 12 months preceding the claim. We
                    are not liable for indirect, incidental or consequential loss.
                </p>

                <h2>10. Governing law</h2>
                <p>
                    These Terms are governed by the laws of Western Australia, Australia, and any dispute is
                    subject to the exclusive jurisdiction of its courts.
                </p>

                <h2>11. Changes to these Terms</h2>
                <p>
                    We may update these Terms from time to time. Continued use of DVARO after a change takes
                    effect constitutes acceptance of the updated Terms.
                </p>

                <h2>12. Contact us</h2>
                <p>
                    Questions about these Terms can be sent to
                    <a href="mailto:hello@dvaro.com.au">hello@dvaro.com.au</a>.
                </p>

                <p class="text-sm text-ink-400">
                    This page is a general-purpose draft and has not yet been reviewed by a lawyer. It should be
                    reviewed and finalised before being relied upon as DVARO's complete terms of service.
                </p>
            </div>
        </section>
    </PublicLayout>
</template>
