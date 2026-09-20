{{--
    COMPACT — tight leading, small type, header and billing details side by side
    in one block. Built for long invoices: a month of daily rental lines fits on
    one page instead of spilling onto a second.
--}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $template['title'] }} {{ $number }}</title>
    <style>
        @page { margin: 26px 32px; }
        body { margin: 0; font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #1a1a1a; line-height: 1.35; }
        h1 { font-size: 16px; margin: 0; color: {{ $accent }}; }
        h2 { font-size: 10px; margin: 12px 0 4px; color: #ffffff; background-color: {{ $accent }}; padding: 3px 6px; text-transform: uppercase; letter-spacing: .06em; }
        table { width: 100%; border-collapse: collapse; }
        td, th { padding: 3px 6px; vertical-align: top; text-align: left; }
        th { border-bottom: 1px solid #ccc; color: #555; font-size: 9px; text-transform: uppercase; }
        .num { text-align: right; }
        .muted { color: #6b6b6b; }
        .wrap { line-height: 1.45; }
        .head td { padding: 0 6px 0 0; vertical-align: top; }
        .company { font-size: 9px; line-height: 1.45; color: #555; }
        .panel { border: 1px solid #e2e2e2; padding: 6px 8px; }
        .bill td.label { width: 36%; color: #666; }
        .items tbody tr:nth-child(even) td { background-color: #fafafa; }
        .totals { margin-top: 6px; }
        .totals td { padding: 2px 6px; }
        .totals td.label { width: 72%; color: #555; }
        .totals td.value { font-weight: bold; }
        .due { font-size: 12px; font-weight: bold; color: {{ $accent }}; border-top: 1px solid {{ $accent }} !important; }
        .thanks { margin-top: 10px; font-size: 9px; color: #666; }
        .logo { max-height: 34px; max-width: 140px; }
        /* Screen only (the settings preview): browsers ignore @page, so the
           sheet would sit flush against the iframe edge without this. dompdf
           ignores @media screen, so the PDF is untouched. */
        @media screen { body { padding: 26px 32px; } }
    </style>
</head>
<body>
    <table class="head">
        <tr>
            <td style="width: 34%;">
                @if ($template['logo'])
                    <img src="{{ $template['logo'] }}" class="logo" alt="">
                @endif
                <h1 style="margin-top: {{ $template['logo'] ? '4px' : '0' }};">{{ $template['title'] }}</h1>
                <div class="muted">{{ $number }} &middot; {{ ucfirst($invoice->status) }}</div>
            </td>
            <td style="width: 33%;">
                @if ($template['show_company_details'])
                    <div class="panel company">
                        <strong>{{ $company['name'] }}</strong><br>
                        @if ($company['abn'])ABN {{ $company['abn'] }}<br>@endif
                        @if ($company['address']){{ $company['address'] }}<br>@endif
                        @if ($company['phone']){{ $company['phone'] }}<br>@endif
                        @if ($company['email']){{ $company['email'] }}@endif
                    </div>
                @endif
            </td>
            <td style="width: 33%;">
                <div class="panel company">
                    <strong>{{ $invoice->customer?->name ?? '—' }}</strong><br>
                    @if ($invoice->customer?->email){{ $invoice->customer->email }}<br>@endif
                    {{ $date($invoice->billing_period_start) }} – {{ $date($invoice->billing_period_end) }}<br>
                    Due {{ $date($invoice->due_date) }}
                </div>
            </td>
        </tr>
    </table>

    @if ($template['intro'])
        <p class="wrap" style="margin-top: 10px;">{!! nl2br(e($template['intro'])) !!}</p>
    @endif

    <h2>Items</h2>
    @include('pdf.invoices._items')
    @include('pdf.invoices._totals')
    @include('pdf.invoices._payments')
    @include('pdf.invoices._footer')
</body>
</html>
