<?php

namespace App\Modules\Agreement\Http\Requests;

use App\Jobs\SendAgreementSigningLinkJob;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Send (or resend) the review-and-sign link to the customer. WhatsApp is only
 * offered when the tenant has WhatsApp notifications switched on — same gate
 * SendLeadFormLinkRequest applies to SMS.
 *
 * The customer's own email/phone are used (never an arbitrary recipient —
 * unlike the lead form, this agreement already belongs to a known customer),
 * so this only asks WHICH channels, not who to send to. The controller
 * validates each requested channel actually has a contact method to use.
 */
class SendForSigningRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $channels = [SendAgreementSigningLinkJob::CHANNEL_EMAIL];

        if ((bool) (app('current_tenant')->settings['notify_whatsapp_enabled'] ?? false)) {
            $channels[] = SendAgreementSigningLinkJob::CHANNEL_WHATSAPP;
        }

        return [
            'channels' => ['required', 'array', 'min:1'],
            'channels.*' => [Rule::in($channels)],
        ];
    }
}
