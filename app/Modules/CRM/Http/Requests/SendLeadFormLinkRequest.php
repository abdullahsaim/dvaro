<?php

namespace App\Modules\CRM\Http\Requests;

use App\Jobs\SendLeadFormLinkJob;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Send the public lead-form link to one person. SMS is only allowed when the
 * tenant has SMS notifications switched on.
 */
class SendLeadFormLinkRequest extends FormRequest
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
        $channels = [SendLeadFormLinkJob::CHANNEL_EMAIL];

        if ((bool) (app('current_tenant')->settings['notify_sms_enabled'] ?? false)) {
            $channels[] = SendLeadFormLinkJob::CHANNEL_SMS;
        }

        return [
            'channel' => ['required', Rule::in($channels)],
            'recipient' => $this->input('channel') === SendLeadFormLinkJob::CHANNEL_SMS
                ? ['required', 'string', 'max:30', 'regex:/^\+?[0-9 ()-]{8,20}$/']
                : ['required', 'email', 'max:255'],
        ];
    }
}
