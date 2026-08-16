// Bundled default images for CMS image blocks.
//
// Image blocks store an S3 path once a super admin uploads a replacement; until
// then the block's value is null. These repo-committed assets (served from the
// Laravel public dir at /images/landing/...) are the fallback shown on the
// public site AND in the super admin CMS editor preview, so the landing page is
// never empty and the editor always shows what is currently live.
//
// Keyed by CMS block key — must stay in sync with the image blocks seeded in
// database/seeders/CmsContentSeeder.php.
export const defaultImages = {
    logo_light: '/images/landing/logo-black.png',
    logo_dark: '/images/landing/logo-white.png',
    hero_image: '/images/landing/hero.jpg',
    feature_1_image: '/images/landing/feature-fleet.svg',
    feature_2_image: '/images/landing/feature-agreement.svg',
    feature_3_image: '/images/landing/feature-invoice.svg',
    feature_4_image: '/images/landing/feature-workshop.svg',
    feature_5_image: '/images/landing/feature-portal.svg',
    feature_6_image: '/images/landing/feature-reports.svg',
    about_image: '/images/landing/about.svg',
};

// Resolve an image block to a displayable URL: the uploaded value when set,
// otherwise the bundled default, otherwise null.
export function cmsImage(key, value) {
    return value || defaultImages[key] || null;
}
