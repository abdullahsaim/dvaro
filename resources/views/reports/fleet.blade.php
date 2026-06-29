{{-- Fleet utilisation report PDF — FUNCTIONAL ONLY, design pass later. --}}
@include('reports.partials.head', ['title' => $title, 'tenant' => $tenant, 'generatedAt' => $generatedAt])

    <h2>Fleet utilisation</h2>
    <table>
        <thead>
            <tr>
                <th>Vehicle</th>
                <th class="num">Total days</th>
                <th class="num">Days rented</th>
                <th class="num">Utilisation</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($data['rows'] ?? [] as $row)
                <tr>
                    <td>{{ $row['vehicle'] }}</td>
                    <td class="num">{{ $row['total_days'] }}</td>
                    <td class="num">{{ $row['days_rented'] }}</td>
                    <td class="num">{{ $row['utilisation'] }}%</td>
                </tr>
            @empty
                <tr><td colspan="4">No vehicles in the fleet.</td></tr>
            @endforelse
        </tbody>
    </table>

@include('reports.partials.foot')
