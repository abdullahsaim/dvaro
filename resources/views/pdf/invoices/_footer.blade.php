{{--
    Payment instructions, footer note and thank-you — the company's own wording
    (Settings → Invoices). Plain text: newlines become <br>, everything is
    escaped, nothing the tenant typed can inject markup into a customer's PDF.
--}}
@if ($template['payment_instructions'])
    <h2>How to pay</h2>
    <p class="wrap">{!! nl2br(e($template['payment_instructions'])) !!}</p>
@endif

@if ($template['footer_note'])
    <p class="muted wrap">{!! nl2br(e($template['footer_note'])) !!}</p>
@endif

@if ($template['thank_you'])
    <p class="thanks">{{ $template['thank_you'] }}</p>
@endif
