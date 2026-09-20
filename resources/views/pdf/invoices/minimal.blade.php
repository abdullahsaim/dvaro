{{--
    MINIMAL — no rules, no bands, generous whitespace, the amount due stated
    once and largely. For companies whose paperwork is deliberately quiet.
--}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $template['title'] }} {{ $number }}</title>
    <style>
        @page { margin: 56px 56px; }
        body { margin: 0; font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #2a2a2a; line-height: 1.5; }
        h1 { font-size: 15px; margin: 0; font-weight: normal; letter-spacing: .14em; text-transform: uppercase; color: {{ $accent }}; }
        h2 { font-size: 10px; margin: 26px 0 8px; color: #999; text-transform: uppercase; letter-spacing: .12em; font-weight: normal; }
        table { width: 100%; border-collapse: collapse; }
        td, th { padding: 6px 0; vertical-align: top; text-align: left; }
        th { color: #999; font-size: 10px; font-weight: normal; text-transform: uppercase; letter-spacing: .1em; }
        .num { text-align: right; }
        .muted { color: #888; }
        .wrap { line-height: 1.6; }
        .head td { padding: 0; vertical-align: top; }
        .company { font-size: 11px; color: #777; line-height: 1.6; }
        .bill td.label { width: 34%; color: #999; }
        .amount { margin: 26px 0 4px; font-size: 30px; color: {{ $accent }}; }
        .amount-label { font-size: 10px; color: #999; text-transform: uppercase; letter-spacing: .12em; }
        .items tbody tr td { border-bottom: 1px solid #f4f4f4; }
        .totals td { padding: 3px 0; color: #777; }
        .totals td.value { color: #2a2a2a; }
        .totals td.label { width: 70%; }
        .due { font-weight: bold; color: {{ $accent }}; }
        .thanks { margin-top: 26px; color: #999; }
        .logo { max-height: 42px; max-width: 160px; }
        /* Screen only (the settings preview): browsers ignore @page, so the
           sheet would sit flush against the iframe edge without this. dompdf
           ignores @media screen, so the PDF is untouched. */
        @media screen { body { padding: 56px 56px; } }
    </style>
</head>
<body>
    <table class="head">
        <tr>
            <td style="width: 55%;">
                @if ($template['logo'])
                    <img src="{{ $template['logo'] }}" class="logo" alt="">
                @endif
                <h1 style="margin-top: {{ $template['logo'] ? '10px' : '0' }};">{{ $template['title'] }}</h1>
                <div class="muted" style="font-size: 11px;">{{ $number }}</div>
            </td>
            <td style="width: 45%; text-align: right;">
                @if ($template['show_company_details'])
                    <div class="company">
                        {{ $company['name'] }}<br>
                        @if ($company['abn'])ABN {{ $company['abn'] }}<br>@endif
                        @if ($company['address']){{ $company['address'] }}<br>@endif
                        @if ($company['email']){{ $company['email'] }}@endif
                    </div>
                @endif
            </td>
        </tr>
    </table>

    <div class="amount-label">Amount due</div>
    <div class="amount">{{ $money($outstanding) }}</div>
    <div class="muted" style="font-size: 11px;">
        Due {{ $date($invoice->due_date) }}
        @if ($gst !== null) &middot; includes GST of {{ $money($gst) }} @endif
    </div>

    @if ($template['intro'])
        <p class="wrap" style="margin-top: 20px;">{!! nl2br(e($template['intro'])) !!}</p>
    @endif

    <h2>Billed to</h2>
    <table class="bill">
        <tr>
            <td class="label">Customer</td>
            <td>{{ $invoice->customer?->name ?? '—' }}</td>
        </tr>
        <tr>
            <td class="label">Period</td>
            <td>{{ $date($invoice->billing_period_start) }} – {{ $date($invoice->billing_period_end) }}</td>
        </tr>
    </table>

    <h2>Items</h2>
    @include('pdf.invoices._items')
    @include('pdf.invoices._totals')
    @include('pdf.invoices._payments')
    @include('pdf.invoices._footer')
</body>
</html>
