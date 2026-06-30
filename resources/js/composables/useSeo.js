// Lightweight SEO helper for the public marketing pages. Builds the document
// title plus a deduplicated set of meta tags (description + Open Graph) that
// each Public page renders inside Inertia's <Head>.
//
// Inertia merges head elements by their `head-key`, so reusing the same keys
// across pages means navigating swaps the tags rather than stacking them.
//
// Usage in a page:
//   const seo = useSeo({ title: '…', description: '…' });
//   <Head :title="seo.title">
//     <meta v-for="m in seo.meta" :key="m.key" :head-key="m.key"
//           :name="m.name" :property="m.property" :content="m.content" />
//   </Head>

export function useSeo({ title, description, ogTitle, ogDescription } = {}) {
    const desc = description ?? '';
    const meta = [];

    if (desc) {
        meta.push({ key: 'description', name: 'description', content: desc });
        meta.push({ key: 'og:description', property: 'og:description', content: ogDescription ?? desc });
    }

    meta.push({ key: 'og:title', property: 'og:title', content: ogTitle ?? title ?? 'DVARO' });
    meta.push({ key: 'og:type', property: 'og:type', content: 'website' });

    return { title: title ?? null, meta };
}
