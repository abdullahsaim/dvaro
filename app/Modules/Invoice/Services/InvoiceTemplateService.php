<?php

namespace App\Modules\Invoice\Services;

use App\Modules\SaasCore\Models\Tenant;
use App\Modules\SaasCore\Services\TenantSettingsService;
use App\Services\BaseService;
use App\Services\TenantBranding;

/**
 * How a company's invoices LOOK: one of four ready-made layouts, their own logo
 * and accent colour, and the wording around the numbers.
 *
 * Client request #4. The numbers themselves are never touched here — this
 * service only decides presentation, so a template change can never alter what
 * anyone owes.
 *
 * Text is stored as PLAIN TEXT, never HTML: it is printed into a PDF that
 * reaches customers, and there is no editing reason to allow markup. Every
 * value is length-capped and control characters are stripped.
 */
class InvoiceTemplateService extends BaseService
{
    public const LAYOUT_CLASSIC = 'classic';

    public const LAYOUT_MODERN = 'modern';

    public const LAYOUT_MINIMAL = 'minimal';

    public const LAYOUT_COMPACT = 'compact';

    public const LAYOUTS = [
        self::LAYOUT_CLASSIC,
        self::LAYOUT_MODERN,
        self::LAYOUT_MINIMAL,
        self::LAYOUT_COMPACT,
    ];

    /** Longest each free-text field may be, in characters. */
    public const LIMITS = [
        'title' => 60,
        'intro' => 300,
        'payment_instructions' => 600,
        'footer_note' => 300,
        'thank_you' => 120,
    ];

    /**
     * The template as it ships. A company that never opens the screen gets
     * this — which is the old invoice, plus the company details that were
     * always missing from it.
     */
    public const DEFAULTS = [
        'layout' => self::LAYOUT_CLASSIC,
        'accent_colour' => null,   // null = follow the company brand colour
        'logo_path' => null,       // null = the company logo
        'show_logo' => true,
        'show_company_details' => true,
        'title' => null,           // null = "Tax Invoice" / "Invoice" by GST registration
        'intro' => null,
        'payment_instructions' => null,
        'footer_note' => null,
        'thank_you' => 'Thank you for your business.',
    ];

    /** GST is 1/11 of a GST-INCLUSIVE amount (Australia). */
    public const GST_DIVISOR = 11;

    public function __construct(
        private readonly TenantSettingsService $settings,
        private readonly TenantBranding $branding,
    ) {}

    /**
     * Everything the invoice Blade needs, resolved: the layout, the colour, an
     * absolute logo path dompdf can read off disk, the wording, and the
     * company's own details.
     *
     * @return array<string, mixed>
     */
    public function resolve(Tenant $tenant, bool $forScreen = false): array
    {
        $all = $this->settings->all($tenant);
        $template = [...self::DEFAULTS, ...(is_array($all['invoice_template'] ?? null) ? $all['invoice_template'] : [])];

        $registered = (bool) $all['gst_registered'];

        // Logo, colour and company details are the same three things every
        // DVARO document shows, so they come from the shared service; only the
        // invoice-specific overrides are decided here.
        $branding = $this->branding->forDocument(
            $tenant,
            logoOverride: $template['logo_path'] ?? null,
            colourOverride: $template['accent_colour'] ?? null,
            forScreen: $forScreen,
        );

        return [
            'layout' => in_array($template['layout'], self::LAYOUTS, true) ? $template['layout'] : self::LAYOUT_CLASSIC,
            'accent' => $branding['accent'],
            'logo' => $template['show_logo'] ? $branding['logo'] : null,
            'title' => $this->text($template['title'], 'title') ?? ($registered ? 'Tax Invoice' : 'Invoice'),
            'intro' => $this->text($template['intro'], 'intro'),
            'payment_instructions' => $this->text($template['payment_instructions'], 'payment_instructions'),
            'footer_note' => $this->text($template['footer_note'], 'footer_note') ?? $this->text($all['invoice_footer_note'], 'footer_note'),
            'thank_you' => $this->text($template['thank_you'], 'thank_you'),
            'show_company_details' => (bool) $template['show_company_details'],
            'gst_registered' => $registered,
            'company' => $branding['company'],
            'date_format' => $branding['date_format'],
            'invoice_prefix' => $all['invoice_prefix'],
        ];
    }

    /** The stored template as the settings screen shows it (no resolution). */
    public function forUi(Tenant $tenant): array
    {
        $stored = $this->settings->get($tenant, 'invoice_template');

        return [...self::DEFAULTS, ...(is_array($stored) ? $stored : [])];
    }

    /**
     * Normalise submitted input: known keys only, colours checked, text capped
     * and stripped of markup and control characters.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function sanitize(array $input): array
    {
        $clean = [
            'layout' => in_array($input['layout'] ?? null, self::LAYOUTS, true)
                ? $input['layout']
                : self::DEFAULTS['layout'],
            'accent_colour' => $this->branding->colour($input['accent_colour'] ?? null),
            'show_logo' => filter_var($input['show_logo'] ?? true, FILTER_VALIDATE_BOOLEAN),
            'show_company_details' => filter_var($input['show_company_details'] ?? true, FILTER_VALIDATE_BOOLEAN),
        ];

        foreach (array_keys(self::LIMITS) as $field) {
            $clean[$field] = $this->text($input[$field] ?? null, $field);
        }

        return $clean;
    }

    /**
     * GST contained in a GST-INCLUSIVE amount, rounded to the cent. Matches the
     * convention the Expenses module already uses.
     */
    public function gstOf(int $cents): int
    {
        return (int) round($cents / self::GST_DIVISOR);
    }

    /** The invoice's customer-facing number, e.g. "INV-1042". */
    public function number(Tenant $tenant, int $invoiceId): string
    {
        $prefix = $this->settings->get($tenant, 'invoice_prefix');

        return trim((string) $prefix).$invoiceId;
    }

    /** Plain text only: tags stripped, control characters removed, capped. */
    private function text(mixed $value, string $field): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $clean = strip_tags($value);
        // Keep newlines in the multi-line fields; drop every other control char.
        $clean = preg_replace('/[^\P{C}\n]+/u', '', $clean) ?? '';
        $clean = trim(preg_replace('/\n{3,}/', "\n\n", $clean) ?? '');

        if ($clean === '') {
            return null;
        }

        return mb_substr($clean, 0, self::LIMITS[$field]);
    }
}
