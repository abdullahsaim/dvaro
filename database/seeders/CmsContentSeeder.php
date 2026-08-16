<?php

namespace Database\Seeders;

use App\Modules\CMS\Models\CmsContentBlock;
use Illuminate\Database\Seeder;

/**
 * Seeds the default landing-page CMS content.
 *
 * IDEMPOTENT — firstOrCreate by `key`, so running it repeatedly never
 * duplicates or overwrites blocks the super admin has since edited. Safe to
 * run after every deploy. NOT wired into DatabaseSeeder (matching the seeder
 * convention here) — run on demand: php artisan db:seed --class=CmsContentSeeder
 *
 * Marketing copy is English (the public pages are CMS-driven, English in v1).
 * The platform's actual pricing is NEVER seeded here — the pricing section
 * always reads live Plan data.
 */
class CmsContentSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->blocks() as $block) {
            CmsContentBlock::query()->firstOrCreate(
                ['key' => $block['key']],
                [
                    'type' => $block['type'],
                    'content' => $block['content'] ?? null,
                    'image_path' => $block['image_path'] ?? null,
                    'section' => $block['section'],
                    'sort_order' => $block['sort_order'] ?? 0,
                ],
            );
        }
    }

    /**
     * The full default content set, grouped by section.
     *
     * @return array<int, array<string, mixed>>
     */
    private function blocks(): array
    {
        $text = CmsContentBlock::TYPE_TEXT;
        $rich = CmsContentBlock::TYPE_RICHTEXT;
        $image = CmsContentBlock::TYPE_IMAGE;

        return [
            // ---- Branding (header/footer logos, theme-specific) ----
            // No image_path seeded: until the super admin uploads a replacement,
            // the frontend falls back to the repo-committed defaults in
            // resources/js/cms/defaultImages.js (logo-black.png / logo-white.png).
            ['key' => 'logo_light', 'type' => $image, 'section' => 'branding', 'sort_order' => 1],
            ['key' => 'logo_dark', 'type' => $image, 'section' => 'branding', 'sort_order' => 2],

            // ---- Hero ----
            ['key' => 'hero_heading', 'type' => $text, 'section' => 'hero', 'sort_order' => 1,
                'content' => 'Run your rental fleet, not your spreadsheets.'],
            ['key' => 'hero_subheading', 'type' => $text, 'section' => 'hero', 'sort_order' => 2,
                'content' => 'DVARO is the all-in-one platform for car rental companies — fleet, agreements, invoicing, workshop and customer portal in one place.'],
            ['key' => 'hero_cta_text', 'type' => $text, 'section' => 'hero', 'sort_order' => 3,
                'content' => 'Start free trial'],
            ['key' => 'hero_image', 'type' => $image, 'section' => 'hero', 'sort_order' => 4],
            ['key' => 'hero_badge', 'type' => $text, 'section' => 'hero', 'sort_order' => 5,
                'content' => 'Built for Australian rental operators'],

            // ---- Stats band (4 value/label pairs, shown on home + about) ----
            ['key' => 'stat_1_value', 'type' => $text, 'section' => 'stats', 'sort_order' => 1,
                'content' => '500+'],
            ['key' => 'stat_1_label', 'type' => $text, 'section' => 'stats', 'sort_order' => 2,
                'content' => 'Vehicles managed'],
            ['key' => 'stat_2_value', 'type' => $text, 'section' => 'stats', 'sort_order' => 3,
                'content' => '12,000+'],
            ['key' => 'stat_2_label', 'type' => $text, 'section' => 'stats', 'sort_order' => 4,
                'content' => 'Agreements signed'],
            ['key' => 'stat_3_value', 'type' => $text, 'section' => 'stats', 'sort_order' => 5,
                'content' => '98%'],
            ['key' => 'stat_3_label', 'type' => $text, 'section' => 'stats', 'sort_order' => 6,
                'content' => 'Invoices paid on time'],
            ['key' => 'stat_4_value', 'type' => $text, 'section' => 'stats', 'sort_order' => 7,
                'content' => '24/7'],
            ['key' => 'stat_4_label', 'type' => $text, 'section' => 'stats', 'sort_order' => 8,
                'content' => 'Customer portal access'],

            // ---- Features (6 blocks: title + description each) ----
            ['key' => 'feature_1_title', 'type' => $text, 'section' => 'features', 'sort_order' => 1,
                'content' => 'Fleet management'],
            ['key' => 'feature_1_description', 'type' => $text, 'section' => 'features', 'sort_order' => 2,
                'content' => 'Track every vehicle, its status, documents and service schedule — with a QR code on each car.'],
            ['key' => 'feature_1_image', 'type' => $image, 'section' => 'features', 'sort_order' => 3],

            ['key' => 'feature_2_title', 'type' => $text, 'section' => 'features', 'sort_order' => 4,
                'content' => 'Digital agreements'],
            ['key' => 'feature_2_description', 'type' => $text, 'section' => 'features', 'sort_order' => 5,
                'content' => 'Generate, sign and version rental agreements on screen. Every change is captured immutably.'],
            ['key' => 'feature_2_image', 'type' => $image, 'section' => 'features', 'sort_order' => 6],

            ['key' => 'feature_3_title', 'type' => $text, 'section' => 'features', 'sort_order' => 7,
                'content' => 'Smart invoicing'],
            ['key' => 'feature_3_description', 'type' => $text, 'section' => 'features', 'sort_order' => 8,
                'content' => 'Recurring billing, prorated vehicle changes and automated late fees — accurate to the cent.'],
            ['key' => 'feature_3_image', 'type' => $image, 'section' => 'features', 'sort_order' => 9],

            ['key' => 'feature_4_title', 'type' => $text, 'section' => 'features', 'sort_order' => 10,
                'content' => 'Workshop & maintenance'],
            ['key' => 'feature_4_description', 'type' => $text, 'section' => 'features', 'sort_order' => 11,
                'content' => 'A QR-driven mechanic portal for service logs, parts and labour, with full cost tracking.'],
            ['key' => 'feature_4_image', 'type' => $image, 'section' => 'features', 'sort_order' => 12],

            ['key' => 'feature_5_title', 'type' => $text, 'section' => 'features', 'sort_order' => 13,
                'content' => 'Customer portal'],
            ['key' => 'feature_5_description', 'type' => $text, 'section' => 'features', 'sort_order' => 14,
                'content' => 'Let customers view invoices, download signed agreements and pay online from their own login.'],
            ['key' => 'feature_5_image', 'type' => $image, 'section' => 'features', 'sort_order' => 15],

            ['key' => 'feature_6_title', 'type' => $text, 'section' => 'features', 'sort_order' => 16,
                'content' => 'Reports & AI insights'],
            ['key' => 'feature_6_description', 'type' => $text, 'section' => 'features', 'sort_order' => 17,
                'content' => 'Revenue, utilisation and overdue analytics with PDF/Excel exports, plus an AI assistant on your data.'],
            ['key' => 'feature_6_image', 'type' => $image, 'section' => 'features', 'sort_order' => 18],

            // ---- How it works (4 numbered steps on the homepage) ----
            ['key' => 'how_1_title', 'type' => $text, 'section' => 'how_it_works', 'sort_order' => 1,
                'content' => 'Register your company'],
            ['key' => 'how_1_description', 'type' => $text, 'section' => 'how_it_works', 'sort_order' => 2,
                'content' => 'Create your workspace in minutes — no sales call required. Start on a free trial.'],
            ['key' => 'how_2_title', 'type' => $text, 'section' => 'how_it_works', 'sort_order' => 3,
                'content' => 'Add your fleet'],
            ['key' => 'how_2_description', 'type' => $text, 'section' => 'how_it_works', 'sort_order' => 4,
                'content' => 'Import vehicles with documents, insurance and rego dates. Each car gets its own QR code.'],
            ['key' => 'how_3_title', 'type' => $text, 'section' => 'how_it_works', 'sort_order' => 5,
                'content' => 'Sign agreements digitally'],
            ['key' => 'how_3_description', 'type' => $text, 'section' => 'how_it_works', 'sort_order' => 6,
                'content' => 'Send rental agreements for on-screen signature. Every version is captured immutably.'],
            ['key' => 'how_4_title', 'type' => $text, 'section' => 'how_it_works', 'sort_order' => 7,
                'content' => 'Get paid automatically'],
            ['key' => 'how_4_description', 'type' => $text, 'section' => 'how_it_works', 'sort_order' => 8,
                'content' => 'Recurring invoices, online payments and automated late fees keep cash flowing.'],

            // ---- About ----
            ['key' => 'about_heading', 'type' => $text, 'section' => 'about', 'sort_order' => 1,
                'content' => 'Built for rental operators, by people who know the work.'],
            ['key' => 'about_body', 'type' => $rich, 'section' => 'about', 'sort_order' => 2,
                'content' => '<p>DVARO brings every part of a car rental business into one accurate system — from the first lead to the final invoice. We obsess over the details that matter: immutable agreements, an append-only ledger, and tenant data that never leaks.</p><p>Whether you run five cars or five hundred, DVARO scales with you while keeping your data isolated and secure.</p>'],
            ['key' => 'about_image', 'type' => $image, 'section' => 'about', 'sort_order' => 3],
            ['key' => 'about_mission', 'type' => $rich, 'section' => 'about', 'sort_order' => 4,
                'content' => '<p>Our mission is simple: give rental operators the same calibre of software the big fleets have, at a price an independent business can justify — accurate to the cent, secure by default, and pleasant to use every single day.</p>'],

            // ---- Values (about page — 4 title/description pairs) ----
            ['key' => 'value_1_title', 'type' => $text, 'section' => 'values', 'sort_order' => 1,
                'content' => 'Accuracy over speed'],
            ['key' => 'value_1_description', 'type' => $text, 'section' => 'values', 'sort_order' => 2,
                'content' => 'This system handles real money, real agreements and real fleets. Every ledger entry balances, every agreement version is preserved.'],
            ['key' => 'value_2_title', 'type' => $text, 'section' => 'values', 'sort_order' => 3,
                'content' => 'Your data is yours'],
            ['key' => 'value_2_description', 'type' => $text, 'section' => 'values', 'sort_order' => 4,
                'content' => 'Every tenant is isolated at the database level. Your customers, pricing and history are never visible to anyone else.'],
            ['key' => 'value_3_title', 'type' => $text, 'section' => 'values', 'sort_order' => 5,
                'content' => 'Built for the real workshop'],
            ['key' => 'value_3_description', 'type' => $text, 'section' => 'values', 'sort_order' => 6,
                'content' => 'QR codes on cars, a mechanic portal that works on a phone, and service history that follows the vehicle — not the paperwork.'],
            ['key' => 'value_4_title', 'type' => $text, 'section' => 'values', 'sort_order' => 7,
                'content' => 'No surprises'],
            ['key' => 'value_4_description', 'type' => $text, 'section' => 'values', 'sort_order' => 8,
                'content' => 'Transparent pricing, hard plan limits you can see, and an upgrade path that never holds your data hostage.'],

            // ---- Contact ----
            ['key' => 'contact_email', 'type' => $text, 'section' => 'contact', 'sort_order' => 1,
                'content' => 'hello@dvaro.com.au'],
            ['key' => 'contact_phone', 'type' => $text, 'section' => 'contact', 'sort_order' => 2,
                'content' => '+61 8 0000 0000'],
            ['key' => 'contact_address', 'type' => $text, 'section' => 'contact', 'sort_order' => 3,
                'content' => 'Canning Vale, Western Australia'],
            ['key' => 'contact_hours', 'type' => $text, 'section' => 'contact', 'sort_order' => 4,
                'content' => 'Mon–Fri, 9:00am–5:00pm AWST'],
            ['key' => 'contact_response_time', 'type' => $text, 'section' => 'contact', 'sort_order' => 5,
                'content' => 'We usually respond within one business day.'],

            // ---- Testimonials (optional — seeded with two examples) ----
            ['key' => 'testimonial_1_quote', 'type' => $text, 'section' => 'testimonials', 'sort_order' => 1,
                'content' => 'DVARO replaced three different tools and a wall of spreadsheets. Our invoicing is finally accurate.'],
            ['key' => 'testimonial_1_author', 'type' => $text, 'section' => 'testimonials', 'sort_order' => 2,
                'content' => 'Operations Manager, Perth'],

            ['key' => 'testimonial_2_quote', 'type' => $text, 'section' => 'testimonials', 'sort_order' => 3,
                'content' => 'The customer portal alone cut our admin calls in half. Customers love paying online.'],
            ['key' => 'testimonial_2_author', 'type' => $text, 'section' => 'testimonials', 'sort_order' => 4,
                'content' => 'Owner, Fremantle'],

            ['key' => 'testimonial_3_quote', 'type' => $text, 'section' => 'testimonials', 'sort_order' => 5,
                'content' => 'Prorated invoicing on vehicle swaps used to take me an evening with a calculator. Now it just happens.'],
            ['key' => 'testimonial_3_author', 'type' => $text, 'section' => 'testimonials', 'sort_order' => 6,
                'content' => 'Fleet Manager, Canning Vale'],

            // ---- FAQ (6 question/answer pairs — home + pricing) ----
            ['key' => 'faq_1_question', 'type' => $text, 'section' => 'faq', 'sort_order' => 1,
                'content' => 'Do I need a credit card to start the free trial?'],
            ['key' => 'faq_1_answer', 'type' => $text, 'section' => 'faq', 'sort_order' => 2,
                'content' => 'No. Register your company, explore every module, and only add payment details when you are ready to subscribe.'],
            ['key' => 'faq_2_question', 'type' => $text, 'section' => 'faq', 'sort_order' => 3,
                'content' => 'Can my customers really sign agreements on their phone?'],
            ['key' => 'faq_2_answer', 'type' => $text, 'section' => 'faq', 'sort_order' => 4,
                'content' => 'Yes. Agreements are signed on screen with a finger or stylus, and every signed version is stored immutably with a full history.'],
            ['key' => 'faq_3_question', 'type' => $text, 'section' => 'faq', 'sort_order' => 5,
                'content' => 'What happens when I hit my plan\'s vehicle limit?'],
            ['key' => 'faq_3_answer', 'type' => $text, 'section' => 'faq', 'sort_order' => 6,
                'content' => 'You will see a clear upgrade prompt — nothing breaks and no data is lost. Upgrade in a click and keep adding vehicles.'],
            ['key' => 'faq_4_question', 'type' => $text, 'section' => 'faq', 'sort_order' => 7,
                'content' => 'Is my company\'s data separated from other companies?'],
            ['key' => 'faq_4_answer', 'type' => $text, 'section' => 'faq', 'sort_order' => 8,
                'content' => 'Completely. Every tenant is isolated at the database level, backed up daily, and encrypted where it matters.'],
            ['key' => 'faq_5_question', 'type' => $text, 'section' => 'faq', 'sort_order' => 9,
                'content' => 'How do online payments work?'],
            ['key' => 'faq_5_answer', 'type' => $text, 'section' => 'faq', 'sort_order' => 10,
                'content' => 'Customers pay invoices by card through a secure hosted page or their own portal login. Stripe and PayPal are both supported.'],
            ['key' => 'faq_6_question', 'type' => $text, 'section' => 'faq', 'sort_order' => 11,
                'content' => 'Can I change plans or cancel at any time?'],
            ['key' => 'faq_6_answer', 'type' => $text, 'section' => 'faq', 'sort_order' => 12,
                'content' => 'Yes — upgrade, downgrade or cancel from your billing page. Your data stays intact and exportable either way.'],

            // ---- Final CTA band ----
            ['key' => 'cta_heading', 'type' => $text, 'section' => 'cta', 'sort_order' => 1,
                'content' => 'Ready to run a tighter fleet?'],
            ['key' => 'cta_subheading', 'type' => $text, 'section' => 'cta', 'sort_order' => 2,
                'content' => 'Join rental operators across Australia who replaced spreadsheets with DVARO.'],
            ['key' => 'cta_button_text', 'type' => $text, 'section' => 'cta', 'sort_order' => 3,
                'content' => 'Start your free trial'],
        ];
    }
}
