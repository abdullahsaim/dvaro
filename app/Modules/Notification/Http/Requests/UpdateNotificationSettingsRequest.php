<?php

namespace App\Modules\Notification\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the tenant's notification settings. Authorization (tenant_admin
 * only) is enforced in NotificationSettingsController against the tenant guard.
 *
 * Provider option lists mirror NotificationProviderFactory's supported choices.
 * 'log' is always valid — it is the safe default that sends nothing real.
 */
class UpdateNotificationSettingsRequest extends FormRequest
{
    public const EMAIL_PROVIDERS = ['log', 'mailgun', 'resend', 'smtp'];
    public const SMS_PROVIDERS = ['log', 'clicksend', 'cellcast'];

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'email_provider' => ['required', Rule::in(self::EMAIL_PROVIDERS)],
            'sms_provider' => ['required', Rule::in(self::SMS_PROVIDERS)],
            'notify_email_enabled' => ['required', 'boolean'],
            'notify_sms_enabled' => ['required', 'boolean'],
            'notify_whatsapp_enabled' => ['required', 'boolean'],
        ];
    }
}
