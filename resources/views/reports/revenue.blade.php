{{--
    Revenue report PDF — FUNCTIONAL ONLY, design pass later.
    Rendered by ExportService::exportPdf via dompdf (pure PHP). Keep markup
    dompdf-friendly: simple tables, inline styles, no flexbox/grid.
    Monetary values are stored in cents; divide by 100 for display.
--}}
@php
    $money = static fn ($cents) => '$' . number_format(((int) $cents) / 100, 2);
@endphp
@include('reports.partials.head', ['title' => $title, 'tenant' => $tenant, 'generatedAt' => $generatedAt])

    <h2>Revenue by month (payment receipt date)</h2>
    <table>
        <thead>
            <tr><th>Month</th><th class="num">Revenue</th></tr>
        </thead>
        <tbody>
            @forelse ($data['by_period'] ?? [] as $row)
                <tr>
                    <td>{{ $row['month'] }}</td>
                    <td class="num">{{ $money($row['revenue']) }}</td>
                </tr>
            @empty
                <tr><td colspan="2">No revenue in this period.</td></tr>
            @endforelse
        </tbody>
    </table>

    <h2>Revenue by vehicle</h2>
    <table>
        <thead>
            <tr><th>Vehicle</th><th class="num">Days rented</th><th class="num">Revenue</th></tr>
        </thead>
        <tbody>
            @forelse ($data['by_vehicle'] ?? [] as $row)
                <tr>
                    <td>{{ $row['vehicle'] }}</td>
                    <td class="num">{{ $row['days_rented'] }}</td>
                    <td class="num">{{ $money($row['revenue']) }}</td>
                </tr>
            @empty
                <tr><td colspan="3">No vehicle revenue in this period.</td></tr>
            @endforelse
        </tbody>
    </table>

@include('reports.partials.foot')
