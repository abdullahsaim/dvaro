{{-- Expenses report PDF (Session 32) — active expenses only, GST-inclusive totals. --}}
@php
    $money = static fn ($cents) => '$' . number_format(((int) $cents) / 100, 2);
    $totals = $data['totals'] ?? ['count' => 0, 'total' => 0, 'gst' => 0, 'ex_gst' => 0];
@endphp
@include('reports.partials.head', ['title' => $title, 'tenant' => $tenant, 'generatedAt' => $generatedAt])

    <h2>Summary</h2>
    <table>
        <tr><td>Expenses recorded</td><td class="num">{{ $totals['count'] }}</td></tr>
        <tr><td>Total (incl. GST)</td><td class="num">{{ $money($totals['total']) }}</td></tr>
        <tr><td>GST paid</td><td class="num">{{ $money($totals['gst']) }}</td></tr>
        <tr><td>Total (ex GST)</td><td class="num">{{ $money($totals['ex_gst']) }}</td></tr>
    </table>

    <h2>By category</h2>
    <table>
        <thead>
            <tr>
                <th>Category</th>
                <th class="num">Count</th>
                <th class="num">Total</th>
                <th class="num">GST</th>
                <th class="num">Ex GST</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($data['by_category'] ?? [] as $row)
                <tr>
                    <td>{{ $row['category'] }}</td>
                    <td class="num">{{ $row['count'] }}</td>
                    <td class="num">{{ $money($row['total']) }}</td>
                    <td class="num">{{ $money($row['gst']) }}</td>
                    <td class="num">{{ $money($row['ex_gst']) }}</td>
                </tr>
            @empty
                <tr><td colspan="5">No expenses in this period.</td></tr>
            @endforelse
        </tbody>
    </table>

    <h2>By month</h2>
    <table>
        <thead>
            <tr><th>Month</th><th class="num">Total</th><th class="num">GST</th><th class="num">Ex GST</th></tr>
        </thead>
        <tbody>
            @forelse ($data['by_month'] ?? [] as $row)
                <tr>
                    <td>{{ \Carbon\Carbon::createFromFormat('Y-m', $row['month'])->format('M Y') }}</td>
                    <td class="num">{{ $money($row['total']) }}</td>
                    <td class="num">{{ $money($row['gst']) }}</td>
                    <td class="num">{{ $money($row['ex_gst']) }}</td>
                </tr>
            @empty
                <tr><td colspan="4">No expenses in this period.</td></tr>
            @endforelse
        </tbody>
    </table>

@include('reports.partials.foot')
