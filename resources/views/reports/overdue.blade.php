{{-- Overdue payments report PDF — FUNCTIONAL ONLY, design pass later. --}}
@php
    $money = static fn ($cents) => '$' . number_format(((int) $cents) / 100, 2);
@endphp
@include('reports.partials.head', ['title' => $title, 'tenant' => $tenant, 'generatedAt' => $generatedAt])

    <h2>Overdue payments</h2>
    <table>
        <thead>
            <tr>
                <th>Customer</th>
                <th class="num">Invoice total</th>
                <th class="num">Outstanding</th>
                <th class="num">Days overdue</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($data['rows'] ?? [] as $row)
                <tr>
                    <td>{{ $row['customer'] }}</td>
                    <td class="num">{{ $money($row['total']) }}</td>
                    <td class="num">{{ $money($row['outstanding']) }}</td>
                    <td class="num">{{ $row['days_overdue'] }}</td>
                </tr>
            @empty
                <tr><td colspan="4">No overdue payments. </td></tr>
            @endforelse
        </tbody>
    </table>

@include('reports.partials.foot')
