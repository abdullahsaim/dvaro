{{--
    Subtotal → total → GST statement → paid → outstanding.

    The GST line is a STATEMENT about a GST-inclusive total, not an addition to
    it: "Total includes GST of $X". Nothing here recalculates what is owed.
--}}
<table class="totals">
    <tr>
        <td class="label num">Subtotal</td>
        <td class="value num">{{ $money($invoice->subtotal) }}</td>
    </tr>
    <tr>
        <td class="label num">Total</td>
        <td class="value num">{{ $money($invoice->total) }}</td>
    </tr>
    @if ($gst !== null)
        <tr>
            <td class="label num muted">Total includes GST of</td>
            <td class="value num muted">{{ $money($gst) }}</td>
        </tr>
    @endif
    <tr>
        <td class="label num">Paid</td>
        <td class="value num">{{ $money($invoice->paid_amount) }}</td>
    </tr>
    <tr>
        <td class="label num due">Outstanding</td>
        <td class="value num due">{{ $money($outstanding) }}</td>
    </tr>
</table>
