{{--
    Agreement PDF — FUNCTIONAL ONLY, design pass later.
    Rendered by GenerateAgreementPdfJob via dompdf (pure PHP). Keep markup
    dompdf-friendly: simple tables, inline styles, no flexbox/grid.
    Monetary values are stored in cents; divide by 100 for display.
--}}
@php
    $money = static fn ($cents) => '$' . number_format(((int) $cents) / 100, 2);
    $date = static fn ($value) => $value ? \Illuminate\Support\Carbon::parse($value)->format('d/m/Y') : '—';
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Agreement #{{ $agreement->id }} v{{ $agreement->version }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #1a1a1a; }
        h1 { font-size: 20px; margin: 0 0 4px; }
        h2 { font-size: 14px; margin: 20px 0 6px; border-bottom: 1px solid #ccc; padding-bottom: 2px; }
        table { width: 100%; border-collapse: collapse; }
        td { padding: 4px 6px; vertical-align: top; }
        td.label { width: 40%; color: #555; }
        td.value { font-weight: bold; }
        .meta { color: #666; font-size: 11px; }
        .sig { margin-top: 8px; border: 1px solid #ccc; max-width: 320px; }
    </style>
</head>
<body>
    <h1>Rental Agreement</h1>
    <p class="meta">
        Agreement #{{ $agreement->id }} &middot; Version {{ $agreement->version }}
        &middot; Status: {{ ucfirst($agreement->status) }}
        @if ($agreement->parent_agreement_id)
            &middot; Supersedes agreement #{{ $agreement->parent_agreement_id }}
        @endif
    </p>

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

    <h2>Signature</h2>
    <table>
        <tr><td class="label">Signed at</td><td class="value">{{ $agreement->signed_at ? $date($agreement->signed_at) : 'Not signed' }}</td></tr>
    </table>
    @if ($agreement->signature_data)
        <img class="sig" src="{{ $agreement->signature_data }}" alt="Signature">
    @endif
</body>
</html>
