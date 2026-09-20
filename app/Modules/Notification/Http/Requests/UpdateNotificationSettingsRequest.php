<?php

namespace App\Modules\Notification\Http\Requests;

use App\Modules\Notification\Services\NotificationMatrix;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the tenant's notification settings. Authorization (tenant_admin
 * only) is enforced in NotificationSettingsController against the tenant guard.
 *
 * Providers moved to Settings → Integrations; the lists below stay because
 * other code still references them as the supported-provider whitelist.
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
            'notify_email_enabled' => ['required', 'boolean'],
            'notify_sms_enabled' => ['required', 'boolean'],
            'notify_whatsapp_enabled' => ['required', 'boolean'],
            // Fleet reminder digest (rego / insurance / service by date or km).
            'fleet_reminders_enabled' => ['required', 'boolean'],
            'fleet_reminder_days' => ['required', 'integer', 'min:1', 'max:180'],
            'fleet_reminder_km' => ['required', 'integer', 'min:100', 'max:20000'],
            // Per-trigger matrix. Keys are checked here and re-checked by
            // NotificationMatrix::sanitize() before anything is stored.
            'matrix' => ['sometimes', 'array'],
            'matrix.*' => ['array'],
            'matrix.*.*' => ['boolean'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function attributes(): array
    {
        $labels = [];

        foreach (NotificationMatrix::TRIGGERS as $key => $definition) {
            $label = __('notifications.triggers.'.str_replace('.', '_', $key).'.label');

            foreach (array_keys($definition['channels']) as $channel) {
                $labels["matrix.{$key}.{$channel}"] = "{$label} ({$channel})";
            }
        }

        return $labels;
    }
}
