{{--
    Agreement PDF. Rendered by GenerateAgreementPdfJob via dompdf (pure PHP).
    Keep markup dompdf-friendly: simple tables, inline styles, no flexbox/grid.
    Monetary values are stored in cents; divide by 100 for display.

    Carries the company's own letterhead (TenantBranding: logo, accent colour,
    company details) so an agreement looks like it came from the rental company
    rather than from nobody. CONTENT is untouched by any of that — this is a
    signed legal document, and branding may only ever change how it looks.

    Each agreement's PDF is generated once, at signing, and kept. A company that
    rebrands later does NOT retro-change agreements people have already signed,
    which is exactly right: the stored file stays the document they saw.
--}}
@php
    $branding = $branding ?? ['logo' => null, 'accent' => '#1a1a1a', 'company' => [], 'date_format' => 'd/m/Y'];
    $company = $branding['company'];
    $accent = $branding['accent'];

    $money = static fn ($cents) => '$' . number_format(((int) $cents) / 100, 2);
    $date = static function ($value) use ($branding) {
        return $value
            ? \Illuminate\Support\Carbon::parse($value)->format($branding['date_format'] ?? 'd/m/Y')
            : '—';
    };
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Agreement #{{ $agreement->id }} v{{ $agreement->version }}</title>
    <style>
        @page { margin: 34px 40px; }
        /* The page margin comes from @page; a body margin on top of it would
           push the layout off the right edge. */
        body { margin: 0; font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #1a1a1a; }
        h1 { font-size: 20px; margin: 0 0 4px; color: {{ $accent }}; }
        h2 { font-size: 14px; margin: 20px 0 6px; border-bottom: 1px solid #ccc; padding-bottom: 2px; color: {{ $accent }}; }
        .head td { padding: 0; vertical-align: top; }
        .company { font-size: 11px; line-height: 1.5; color: #444; }
        .company strong { font-size: 13px; color: #1a1a1a; }
        .logo { max-height: 56px; max-width: 200px; }
        table { width: 100%; border-collapse: collapse; }
        td { padding: 4px 6px; vertical-align: top; }
        td.label { width: 40%; color: #555; }
        td.value { font-weight: bold; }
        .meta { color: #666; font-size: 11px; }
        .sig { margin-top: 8px; border: 1px solid #ccc; max-width: 320px; }
        .terms { font-size: 11px; line-height: 1.5; }
        .terms h2 { font-size: 13px; margin: 14px 0 4px; }
        .terms h3 { font-size: 12px; margin: 12px 0 4px; }
        .terms p { margin: 0 0 8px; }
        .terms ul, .terms ol { margin: 0 0 8px 18px; padding: 0; }
        .terms li { margin: 0 0 4px; }
    </style>
</head>
<body>
    <table class="head">
        <tr>
            <td style="width: 58%;">
                @if ($branding['logo'])
                    <img src="{{ $branding['logo'] }}" class="logo" alt="">
                @endif
                @if (! empty($company))
                    <div class="company" style="margin-top: 6px;">
                        <strong>{{ $company['name'] ?? '' }}</strong><br>
                        @if (! empty($company['trading_as']))trading as {{ $company['trading_as'] }}<br>@endif
                        @if (! empty($company['abn']))ABN {{ $company['abn'] }}<br>@endif
                        @if (! empty($company['address'])){{ $company['address'] }}<br>@endif
                        @if (! empty($company['phone'])){{ $company['phone'] }}@endif
                        @if (! empty($company['phone']) && ! empty($company['email'])) &middot; @endif
                        @if (! empty($company['email'])){{ $company['email'] }}@endif
                    </div>
                @endif
            </td>
            <td style="width: 42%; text-align: right;">
                <h1>Rental Agreement</h1>
                <p class="meta" style="line-height: 1.6;">
                    Agreement #{{ $agreement->id }} &middot; Version {{ $agreement->version }}<br>
                    {{ ucfirst($agreement->status) }}
                    @if ($agreement->parent_agreement_id)
                        <br>Supersedes agreement #{{ $agreement->parent_agreement_id }}
                    @endif
                </p>
            </td>
        </tr>
    </table>

    <h2>Parties</h2>
    <table>
        <tr>
            <td class="label">Customer</td>
            <td class="value">{{ $agreement->customer?->name ?? '—' }}</td>
        </tr>
        <tr>
            <td class="label">Customer email</td>
            <td class="value">{{ $agreement->customer?->email ?? '—' }}</td>
        </tr>
        <tr>
            <td class="label">Customer phone</td>
            <td class="value">{{ $agreement->customer?->phone ?? '—' }}</td>
        </tr>
        <tr>
            <td class="label">Vehicle</td>
            <td class="value">
                {{ $agreement->vehicle?->registration_number ?? '—' }}
                @if ($agreement->vehicle)
                    ({{ trim(($agreement->vehicle->make ?? '') . ' ' . ($agreement->vehicle->model ?? '')) }})
                @endif
            </td>
        </tr>
    </table>

    <h2>Terms</h2>
    <table>
        <tr><td class="label">Type</td><td class="value">{{ ucfirst($agreement->type) }}</td></tr>
        <tr><td class="label">Billing cycle</td><td class="value">{{ ucfirst($agreement->billing_cycle) }}</td></tr>
        <tr><td class="label">Billing cycle day</td><td class="value">{{ $agreement->billing_cycle_day ?? '—' }}</td></tr>
        <tr><td class="label">Rate</td><td class="value">{{ $money($agreement->rate) }} per {{ $agreement->billing_cycle }}</td></tr>
        <tr><td class="label">Bond / security deposit</td><td class="value">{{ $money($agreement->bond_amount) }}</td></tr>
        <tr><td class="label">Start date</td><td class="value">{{ $date($agreement->start_date) }}</td></tr>
        <tr><td class="label">End date</td><td class="value">{{ $agreement->end_date ? $date($agreement->end_date) : 'No fixed term' }}</td></tr>
        <tr><td class="label">Notes</td><td class="value">{{ $agreement->notes ?? '—' }}</td></tr>
    </table>

    @if (filled($agreement->terms_html))
        {{-- FROZEN terms: merge fields already resolved and the HTML sanitised
             when the template was saved, so this is safe to render raw. --}}
        <h2>Terms and conditions</h2>
        <div class="terms">{!! $agreement->terms_html !!}</div>
    @endif

    <h2>Signature</h2>
    <table>
        <tr><td class="label">Signed at</td><td class="value">{{ $agreement->signed_at ? $date($agreement->signed_at) : 'Not signed' }}</td></tr>
    </table>
    @if ($agreement->signature_data)
        <img class="sig" src="{{ $agreement->signature_data }}" alt="Signature">
    @endif
</body>
</html>
