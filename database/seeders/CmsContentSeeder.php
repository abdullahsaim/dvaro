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
            // ---- Hero ----
            ['key' => 'hero_heading', 'type' => $text, 'section' => 'hero', 'sort_order' => 1,
                'content' => 'Run your rental fleet, not your spreadsheets.'],
            ['key' => 'hero_subheading', 'type' => $text, 'section' => 'hero', 'sort_order' => 2,
                'content' => 'DVARO is the all-in-one platform for car rental companies — fleet, agreements, invoicing, workshop and customer portal in one place.'],
            ['key' => 'hero_cta_text', 'type' => $text, 'section' => 'hero', 'sort_order' => 3,
                'content' => 'Start free trial'],
            ['key' => 'hero_image', 'type' => $image, 'section' => 'hero', 'sort_order' => 4],

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

            // ---- About ----
            ['key' => 'about_heading', 'type' => $text, 'section' => 'about', 'sort_order' => 1,
                'content' => 'Built for rental operators, by people who know the work.'],
            ['key' => 'about_body', 'type' => $rich, 'section' => 'about', 'sort_order' => 2,
                'content' => '<p>DVARO brings every part of a car rental business into one accurate system — from the first lead to the final invoice. We obsess over the details that matter: immutable agreements, an append-only ledger, and tenant data that never leaks.</p><p>Whether you run five cars or five hundred, DVARO scales with you while keeping your data isolated and secure.</p>'],
            ['key' => 'about_image', 'type' => $image, 'section' => 'about', 'sort_order' => 3],

            // ---- Contact ----
            ['key' => 'contact_email', 'type' => $text, 'section' => 'contact', 'sort_order' => 1,
                'content' => 'hello@dvaro.com.au'],
            ['key' => 'contact_phone', 'type' => $text, 'section' => 'contact', 'sort_order' => 2,
                'content' => '+61 8 0000 0000'],
            ['key' => 'contact_address', 'type' => $text, 'section' => 'contact', 'sort_order' => 3,
                'content' => 'Canning Vale, Western Australia'],

            // ---- Testimonials (optional — seeded with two examples) ----
            ['key' => 'testimonial_1_quote', 'type' => $text, 'section' => 'testimonials', 'sort_order' => 1,
                'content' => 'DVARO replaced three different tools and a wall of spreadsheets. Our invoicing is finally accurate.'],
            ['key' => 'testimonial_1_author', 'type' => $text, 'section' => 'testimonials', 'sort_order' => 2,
                'content' => 'Operations Manager, Perth'],

            ['key' => 'testimonial_2_quote', 'type' => $text, 'section' => 'testimonials', 'sort_order' => 3,
                'content' => 'The customer portal alone cut our admin calls in half. Customers love paying online.'],
            ['key' => 'testimonial_2_author', 'type' => $text, 'section' => 'testimonials', 'sort_order' => 4,
                'content' => 'Owner, Fremantle'],
        ];
    }
}
