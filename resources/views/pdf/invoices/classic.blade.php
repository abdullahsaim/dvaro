{{--
    CLASSIC — the traditional invoice: logo and company block top-left, invoice
    details top-right, ruled section headings. The safest choice for a company
    that wants its invoice to look like every other invoice, and the default.
--}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $template['title'] }} {{ $number }}</title>
    <style>
        @page { margin: 34px 40px; }
        body { margin: 0; font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #1a1a1a; }
        h1 { font-size: 22px; margin: 0 0 2px; color: {{ $accent }}; }
        h2 { font-size: 13px; margin: 20px 0 6px; border-bottom: 1px solid #d8d8d8; padding-bottom: 3px; color: {{ $accent }}; }
        table { width: 100%; border-collapse: collapse; }
        td, th { padding: 5px 6px; vertical-align: top; text-align: left; }
        th { border-bottom: 1px solid #ccc; color: #555; font-size: 11px; text-transform: uppercase; letter-spacing: .04em; }
        .num { text-align: right; }
        .muted { color: #6b6b6b; }
        .wrap { line-height: 1.5; }
        .head td { padding: 0; vertical-align: top; }
        .company { font-size: 11px; line-height: 1.5; color: #444; }
        .company strong { font-size: 13px; color: #1a1a1a; }
        .bill td.label { width: 34%; color: #555; }
        .bill td.value { font-weight: bold; }
        .items tbody tr td { border-bottom: 1px solid #f0f0f0; }
        .totals { margin-top: 10px; }
        .totals td { border-top: 1px solid #eee; }
        .totals td.label { width: 70%; color: #555; }
        .totals td.value { font-weight: bold; }
        .due { font-size: 14px; font-weight: bold; color: {{ $accent }}; }
        .thanks { margin-top: 18px; font-style: italic; color: #555; }
        .logo { max-height: 56px; max-width: 200px; }
        /* Screen only (the settings preview): browsers ignore @page, so the
           sheet would sit flush against the iframe edge without this. dompdf
           ignores @media screen, so the PDF is untouched. */
        @media screen { body { padding: 34px 40px; } }
    </style>
</head>
<body>
    <table class="head">
        <tr>
            <td style="width: 60%;">
                @if ($template['logo'])
                    <img src="{{ $template['logo'] }}" class="logo" alt="">
                @endif
                @if ($template['show_company_details'])
                    <div class="company" style="margin-top: 6px;">
                        <strong>{{ $company['name'] }}</strong><br>
                        @if ($company['trading_as'])trading as {{ $company['trading_as'] }}<br>@endif
                        @if ($company['abn'])ABN {{ $company['abn'] }}<br>@endif
                        @if ($company['address']){{ $company['address'] }}<br>@endif
                        @if ($company['phone']){{ $company['phone'] }}@endif
                        @if ($company['phone'] && $company['email']) &middot; @endif
                        @if ($company['email']){{ $company['email'] }}@endif
                    </div>
                @endif
            </td>
            <td style="width: 40%; text-align: right;">
                <h1>{{ $template['title'] }}</h1>
                <div class="muted" style="font-size: 11px; line-height: 1.6;">
                    {{ $number }}<br>
                    Issued {{ $date($invoice->issue_date ?? $invoice->created_at) }}<br>
                    Due {{ $date($invoice->due_date) }}<br>
                    {{ ucfirst($invoice->status) }}
                </div>
            </td>
        </tr>
    </table>

    @if ($template['intro'])
        <p class="wrap" style="margin-top: 18px;">{!! nl2br(e($template['intro'])) !!}</p>
    @endif

    <h2>Bill to</h2>
    <table class="bill">
        <tr>
            <td class="label">Customer</td>
            <td class="value">{{ $invoice->customer?->name ?? '—' }}</td>
        </tr>
        @if ($invoice->customer?->email)
            <tr>
                <td class="label">Email</td>
                <td class="value">{{ $invoice->customer->email }}</td>
            </tr>
        @endif
        <tr>
            <td class="label">Billing period</td>
            <td class="value">{{ $date($invoice->billing_period_start) }} – {{ $date($invoice->billing_period_end) }}</td>
        </tr>
        @if ($invoice->agreement_id)
            <tr>
                <td class="label">Agreement</td>
                <td class="value">#{{ $invoice->agreement_id }}</td>
            </tr>
        @endif
    </table>

    <h2>Items</h2>
    @include('pdf.invoices._items')
    @include('pdf.invoices._totals')
    @include('pdf.invoices._payments')
    @include('pdf.invoices._footer')
</body>
</html>
