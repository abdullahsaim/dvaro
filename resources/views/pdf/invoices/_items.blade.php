{{--
    The line items. Shared by all four layouts so the numbers can never drift
    between them; only $accent and the surrounding styles change.
--}}
<table class="items">
    <thead>
        <tr>
            <th>Description</th>
            <th>Vehicle</th>
            <th>Period</th>
            <th class="num">Amount</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($invoice->items as $item)
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
        @empty
            <tr>
                <td colspan="4" class="muted">No line items.</td>
            </tr>
        @endforelse
    </tbody>
</table>
