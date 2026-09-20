{{--
    Invoice PDF — the SHELL. Picks one of four ready-made layouts (Settings →
    Invoices) and hands each the same prepared data, so every layout shows
    identical numbers and only the presentation differs.

    Rendered by GenerateInvoicePdfJob via dompdf (pure PHP) and by the settings
    preview (HTML, sample data). Keep markup dompdf-friendly: simple tables,
    inline styles, no flexbox/grid, no external assets (the logo is an absolute
    filesystem path, not a URL).

    Monetary values are stored in cents. GST is 1/11 of a GST-INCLUSIVE total,
    computed for display only — nothing stored is ever recalculated here.
--}}
@php
    use App\Modules\Invoice\Services\InvoiceTemplateService;

    $money = static fn ($cents) => '$' . number_format(((int) $cents) / 100, 2);
    $date = static function ($value) use ($template) {
        return $value
            ? \Illuminate\Support\Carbon::parse($value)->format($template['date_format'] ?? 'd/m/Y')
            : '—';
    };

    $outstanding = (int) $invoice->total - (int) $invoice->paid_amount;
    $gst = $template['gst_registered'] ? app(InvoiceTemplateService::class)->gstOf((int) $invoice->total) : null;
    $number = ($template['invoice_prefix'] ?? '') . $invoice->id;
    $accent = $template['accent'];
    $company = $template['company'];

    $layout = 'pdf.invoices.' . $template['layout'];
@endphp
@include($layout, [
    'invoice' => $invoice,
    'template' => $template,
    'company' => $company,
    'accent' => $accent,
    'number' => $number,
    'gst' => $gst,
    'outstanding' => $outstanding,
    'money' => $money,
    'date' => $date,
])
