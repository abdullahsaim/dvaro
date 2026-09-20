{{--
    MODERN — a full-width accent band across the top carrying the logo and the
    invoice number, then a light, borderless body. The one that makes a brand
    colour actually visible.
--}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $template['title'] }} {{ $number }}</title>
    <style>
        @page { margin: 0 0 34px; }
        /* The page margin comes from @page; a body margin on top of it would
           inset the accent band and push the layout off the right edge. */
        body { margin: 0; font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #1a1a1a; }
        .page { padding: 0 40px; }
        .band { background-color: {{ $accent }}; color: #ffffff; padding: 22px 40px 20px; }
        .band h1 { font-size: 24px; margin: 0; color: #ffffff; letter-spacing: .01em; }
        .band .meta { font-size: 11px; line-height: 1.6; color: #ffffff; }
        h2 { font-size: 11px; margin: 22px 0 6px; color: {{ $accent }}; text-transform: uppercase; letter-spacing: .08em; }
        table { width: 100%; border-collapse: collapse; }
        td, th { padding: 6px 6px; vertical-align: top; text-align: left; }
        th { color: #6b6b6b; font-size: 10px; text-transform: uppercase; letter-spacing: .06em; border-bottom: 2px solid {{ $accent }}; }
        .num { text-align: right; }
        .muted { color: #6b6b6b; }
        .wrap { line-height: 1.55; }
        .head td { padding: 0; vertical-align: top; }
        .company { font-size: 11px; line-height: 1.5; color: #555; }
        .bill td.label { width: 34%; color: #6b6b6b; }
        .bill td.value { font-weight: bold; }
        .items tbody tr td { border-bottom: 1px solid #f2f2f2; }
        .totals { margin-top: 12px; }
        .totals td { border: none; padding: 3px 6px; }
        .totals td.label { width: 70%; color: #6b6b6b; }
        .totals td.value { font-weight: bold; }
        .due { font-size: 15px; font-weight: bold; color: {{ $accent }}; border-top: 2px solid {{ $accent }} !important; padding-top: 8px !important; }
        .thanks { margin-top: 20px; font-size: 13px; color: {{ $accent }}; }
        .logo { max-height: 46px; max-width: 180px; }
        /* Screen only (the settings preview): browsers ignore @page, so the
           sheet would sit flush against the iframe edge without this. dompdf
           ignores @media screen, so the PDF is untouched. */
        @media screen { body { padding: 0 0 34px; } }
    </style>
</head>
<body>
    <div class="band">
        <table class="head">
            <tr>
                <td style="width: 60%;">
                    @if ($template['logo'])
                        <img src="{{ $template['logo'] }}" class="logo" alt="">
                    @else
                        <h1>{{ $company['name'] }}</h1>
                    @endif
                </td>
                <td style="width: 40%; text-align: right;">
                    <h1>{{ $template['title'] }}</h1>
                    <div class="meta">
                        {{ $number }} &middot; {{ ucfirst($invoice->status) }}<br>
                        Due {{ $date($invoice->due_date) }}
                    </div>
                </td>
            </tr>
        </table>
    </div>

    <div class="page">
        @if ($template['show_company_details'])
            <table class="head" style="margin-top: 18px;">
                <tr>
                    <td style="width: 55%;">
                        <h2>From</h2>
                        <div class="company">
                            <strong>{{ $company['name'] }}</strong><br>
                            @if ($company['abn'])ABN {{ $company['abn'] }}<br>@endif
                            @if ($company['address']){{ $company['address'] }}<br>@endif
                            @if ($company['phone']){{ $company['phone'] }}<br>@endif
                            @if ($company['email']){{ $company['email'] }}@endif
                        </div>
                    </td>
                    <td style="width: 45%;">
                        <h2>Bill to</h2>
                        <div class="company">
                            <strong>{{ $invoice->customer?->name ?? '—' }}</strong><br>
                            @if ($invoice->customer?->email){{ $invoice->customer->email }}<br>@endif
                            {{ $date($invoice->billing_period_start) }} – {{ $date($invoice->billing_period_end) }}
                        </div>
                    </td>
                </tr>
            </table>
        @else
            <h2>Bill to</h2>
            <table class="bill">
                <tr>
                    <td class="label">Customer</td>
                    <td class="value">{{ $invoice->customer?->name ?? '—' }}</td>
                </tr>
                <tr>
                    <td class="label">Billing period</td>
                    <td class="value">{{ $date($invoice->billing_period_start) }} – {{ $date($invoice->billing_period_end) }}</td>
                </tr>
            </table>
        @endif

        @if ($template['intro'])
            <p class="wrap" style="margin-top: 16px;">{!! nl2br(e($template['intro'])) !!}</p>
        @endif

        <h2>Items</h2>
        @include('pdf.invoices._items')
        @include('pdf.invoices._totals')
        @include('pdf.invoices._payments')
        @include('pdf.invoices._footer')
    </div>
</body>
</html>
