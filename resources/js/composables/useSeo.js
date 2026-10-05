// Lightweight SEO helper for the public marketing pages. Builds the document
// title plus a deduplicated set of meta tags (description + Open Graph) and a
// canonical URL that each Public page renders inside Inertia's <Head>.
//
// Inertia merges head elements by their `head-key`, so reusing the same keys
// across pages means navigating swaps the tags rather than stacking them.
//
// Usage in a page:
//   const seo = useSeo({ title: '…', description: '…' });
//   <Head :title="seo.title">
//     <link rel="canonical" head-key="canonical" :href="seo.canonical" />
//     <meta v-for="m in seo.meta" :key="m.key" :head-key="m.key"
//           :name="m.name" :property="m.property" :content="m.content" />
//   </Head>

import { usePage } from '@inertiajs/vue3';

export function useSeo({ title, description, ogTitle, ogDescription } = {}) {
    const desc = description ?? '';
    const meta = [];

    if (desc) {
        meta.push({ key: 'description', name: 'description', content: desc });
        meta.push({ key: 'og:description', property: 'og:description', content: ogDescription ?? desc });
    }

    meta.push({ key: 'og:title', property: 'og:title', content: ogTitle ?? title ?? 'DVARO' });
    meta.push({ key: 'og:type', property: 'og:type', content: 'website' });

    // Canonical URL — the current path against this deploy's own origin.
    // window is absent only during SSR, which this app does not use, but the
    // guard keeps this safe to import anywhere.
    const path = usePage().url ?? '/';
    const origin = typeof window !== 'undefined' ? window.location.origin : 'https://dvaro.com.au';
    const canonical = origin + path.split('#')[0].split('?')[0];

    meta.push({ key: 'og:url', property: 'og:url', content: canonical });

    return { title: title ?? null, meta, canonical };
}
