{{-- Workshop performance report PDF — FUNCTIONAL ONLY, design pass later. --}}
@php
    $money = static fn ($cents) => '$' . number_format(((int) $cents) / 100, 2);
@endphp
@include('reports.partials.head', ['title' => $title, 'tenant' => $tenant, 'generatedAt' => $generatedAt])

    <h2>Summary</h2>
    <table>
        <tr><td>Total jobs completed</td><td class="num">{{ $data['total_jobs'] ?? 0 }}</td></tr>
        <tr><td>Total labour cost</td><td class="num">{{ $money($data['total_labour_cost'] ?? 0) }}</td></tr>
        <tr><td>Total parts cost</td><td class="num">{{ $money($data['total_parts_cost'] ?? 0) }}</td></tr>
        <tr><td>Average job duration</td><td class="num">{{ $data['average_duration_hours'] ?? 0 }} hrs</td></tr>
    </table>

    <h2>By mechanic</h2>
    <table>
        <thead>
            <tr>
                <th>Mechanic</th>
                <th class="num">Jobs</th>
                <th class="num">Labour</th>
                <th class="num">Parts</th>
                <th class="num">Total</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($data['by_mechanic'] ?? [] as $row)
                <tr>
                    <td>{{ $row['mechanic'] }}</td>
                    <td class="num">{{ $row['jobs'] }}</td>
                    <td class="num">{{ $money($row['labour_cost']) }}</td>
                    <td class="num">{{ $money($row['parts_cost']) }}</td>
                    <td class="num">{{ $money($row['total_cost']) }}</td>
                </tr>
            @empty
                <tr><td colspan="5">No completed jobs in this period.</td></tr>
            @endforelse
        </tbody>
    </table>

@include('reports.partials.foot')
