<?php

namespace App\Modules\Agreement\Services;

use App\Services\BaseService;
use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

/**
 * Server-side HTML allow-list for agreement terms.
 *
 * Terms are written by RENTAL COMPANIES (not platform staff) and are rendered
 * back to their customers (agreement page, customer portal, PDF), so the HTML
 * is sanitised on SAVE — never trusted from the editor. Anything outside the
 * allow-list (script/style/iframe/links/images/on* handlers/javascript: URLs)
 * is dropped; the surviving text is kept.
 *
 * Only plain formatting survives: paragraphs, h2/h3 headings, bold, italic,
 * underline, bullet + numbered lists, and line breaks — everything dompdf
 * renders reliably in the agreement PDF.
 */
class AgreementTermsSanitizer extends BaseService
{
    /** @var list<string> */
    public const ALLOWED_TAGS = ['p', 'br', 'strong', 'b', 'em', 'i', 'u', 'ul', 'ol', 'li', 'h2', 'h3'];

    public function sanitize(string $html): string
    {
        // Start from an EMPTY allow-list (never allowStaticElements(), which
        // would let <a>, <img> and friends through).
        $config = new HtmlSanitizerConfig();

        foreach (self::ALLOWED_TAGS as $tag) {
            $config = $config->allowElement($tag);
        }

        // Symfony DROPS unknown elements together with their contents, which
        // would silently empty terms pasted from Word (<div>/<span> wrappers).
        // Block these instead: the tag goes, the text inside stays.
        foreach ([
            'div', 'span', 'section', 'article', 'main', 'header', 'footer', 'a', 'font',
            'table', 'thead', 'tbody', 'tr', 'td', 'th', 'blockquote', 'pre', 'small',
            'h1', 'h4', 'h5', 'h6', 'sub', 'sup', 'mark', 'label', 'figure', 'figcaption',
        ] as $tag) {
            $config = $config->blockElement($tag);
        }

        // These lose their CONTENT too — a stripped <script> would otherwise
        // leave its code as visible text in the agreement.
        foreach (['script', 'style', 'iframe', 'object', 'embed', 'template', 'head', 'noscript'] as $tag) {
            $config = $config->dropElement($tag);
        }

        $clean = (new HtmlSanitizer($config))->sanitize($html);

        return trim($clean);
    }

    /** True when sanitising would strip everything meaningful (empty terms). */
    public function isEmpty(string $html): bool
    {
        return trim(html_entity_decode(strip_tags($this->sanitize($html)))) === '';
    }
}
