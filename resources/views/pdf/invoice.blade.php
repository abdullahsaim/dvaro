{{--
    Invoice PDF — FUNCTIONAL ONLY, design pass later.
    Rendered by GenerateInvoicePdfJob via dompdf (pure PHP). Keep markup
    dompdf-friendly: simple tables, inline styles, no flexbox/grid.
    Monetary values are stored in cents; divide by 100 for display.
--}}
@php
    $money = static fn ($cents) => '$' . number_format(((int) $cents) / 100, 2);
    $date = static fn ($value) => $value ? \Illuminate\Support\Carbon::parse($value)->format('d/m/Y') : '—';
    $outstanding = (int) $invoice->total - (int) $invoice->paid_amount;
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Invoice #{{ $invoice->id }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #1a1a1a; }
        h1 { font-size: 20px; margin: 0 0 4px; }
        h2 { font-size: 14px; margin: 20px 0 6px; border-bottom: 1px solid #ccc; padding-bottom: 2px; }
        table { width: 100%; border-collapse: collapse; }
        td, th { padding: 4px 6px; vertical-align: top; text-align: left; }
        th { border-bottom: 1px solid #ccc; color: #555; }
        td.num, th.num { text-align: right; }
        td.label { width: 40%; color: #555; }
        td.value { font-weight: bold; }
        .meta { color: #666; font-size: 11px; }
        .totals td { border-top: 1px solid #eee; }
        .totals td.value { font-weight: bold; }
        .due { font-size: 14px; font-weight: bold; }
    </style>
</head>
<body>
    <h1>Tax Invoice</h1>
    <p class="meta">
        Invoice #{{ $invoice->id }} &middot; {{ ucfirst($invoice->type) }}
        &middot; Status: {{ ucfirst($invoice->status) }}
        @if ($invoice->agreement_id)
            &middot; Agreement #{{ $invoice->agreement_id }}
        @endif
    </p>
    <p class="meta">{{ $tenant->name }}</p>

    <h2>Bill to</h2>
    <table>
        <tr>
            <td class="label">Customer</td>
            <td class="value">{{ $invoice->customer?->name ?? '—' }}</td>
        </tr>
        <tr>
            <td class="label">Billing period</td>
            <td class="value">{{ $date($invoice->billing_period_start) }} – {{ $date($invoice->billing_period_end) }}</td>
        </tr>
        <tr>
            <td class="label">Due date</td>
            <td class="value">{{ $date($invoice->due_date) }}</td>
        </tr>
    </table>

    <h2>Items</h2>
    <table>
        <thead>
            <tr>
                <th>Description</th>
                <th>Vehicle</th>
                <th>Period</th>
                <th class="num">Amount</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($invoice->items as $item)
                <tr>
                    <td>{{ $item->description }}</td>
                    <td>{{ $item->vehicle?->registration_number ?? '—' }}</td>
                    <td>
                        @if ($item->period_start)
                            {{ $date($item->period_start) }} – {{ $date($item->period_end) }}
                        @else
                            —
                        @endif
                    </td>
                    <td class="num">{{ $money($item->amount) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="totals">
        <tr>
            <td class="label num">Subtotal</td>
            <td class="value num">{{ $money($invoice->subtotal) }}</td>
        </tr>
        <tr>
            <td class="label num">Total</td>
            <td class="value num">{{ $money($invoice->total) }}</td>
        </tr>
        <tr>
            <td class="label num">Paid</td>
            <td class="value num">{{ $money($invoice->paid_amount) }}</td>
        </tr>
        <tr>
            <td class="label num due">Outstanding</td>
            <td class="value num due">{{ $money($outstanding) }}</td>
        </tr>
    </table>

    @if ($invoice->payments->isNotEmpty())
        <h2>Payments</h2>
        <table>
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Method</th>
                    <th class="num">Amount</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($invoice->payments as $payment)
                    <tr>
                        <td>{{ $date($payment->paid_at) }}</td>
                        <td>{{ ucfirst(str_replace('_', ' ', $payment->method)) }}</td>
                        <td class="num">{{ $money($payment->amount) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</body>
</html>
