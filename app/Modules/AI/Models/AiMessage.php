<?php

namespace App\Modules\AI\Models;

use App\Traits\HasTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * AiMessage — one turn in an AiConversation (a user prompt or an assistant
 * reply). Tenant-restricted via HasTenant. tokens_used / provider are recorded
 * for assistant rows (usage tracking); both are null for user rows and for the
 * Log provider, which reports neither.
 */
class AiMessage extends Model
{
    use HasTenant;

    public const ROLE_USER = 'user';
    public const ROLE_ASSISTANT = 'assistant';

    /** Roles in the OpenAI-style chat message format. */
    public const ROLES = [
        self::ROLE_USER,
        self::ROLE_ASSISTANT,
    ];

    protected $fillable = [
        'tenant_id',
        'conversation_id',
        'role',
        'content',
        'tokens_used',
        'provider',
    ];

    protected function casts(): array
    {
        return [
            'tokens_used' => 'integer',
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(AiConversation::class, 'conversation_id');
    }
}
