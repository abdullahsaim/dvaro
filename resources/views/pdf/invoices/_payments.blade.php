{{-- Payments already recorded against this invoice (omitted when there are none). --}}
@if ($invoice->payments->isNotEmpty())
    <h2>Payments received</h2>
    <table class="items">
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
