<?php

namespace App\Modules\AI\Http\Requests;

use App\Modules\AI\Models\AiConversation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates a chat turn. Authorization (own-conversation + module access) is
 * handled in the controller / middleware — see AiController.
 *
 * conversation_id, when present, must reference a conversation belonging to the
 * CURRENT tenant. The Rule::exists below runs a raw query that bypasses
 * TenantScope, so tenant_id is constrained EXPLICITLY (and soft-deleted threads
 * are excluded). The AiPolicy::chat check then enforces same-user ownership.
 */
class AiChatRequest extends FormRequest
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
        $tenantId = app('current_tenant')->id;

        return [
            'conversation_id' => [
                'nullable',
                'integer',
                Rule::exists('ai_conversations', 'id')
                    ->where('tenant_id', $tenantId)
                    ->whereNull('deleted_at'),
            ],
            'mode' => ['required', Rule::in(AiConversation::MODES)],
            'message' => ['required', 'string', 'max:2000'],
        ];
    }
}
