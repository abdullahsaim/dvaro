<?php

namespace App\Modules\AI\Models;

use App\Modules\SaasCore\Models\TenantUser;
use App\Traits\HasTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * AiConversation — one AI chat thread owned by one tenant user.
 *
 * Tenant-restricted (HasTenant → TenantScope on every query). A conversation is
 * locked to a single mode (Help or Business Intelligence); the title is derived
 * from the first user message. Soft-deleted on destroy (no hard deletes).
 *
 * The AI assistant NEVER exposes data across tenants: messages, history, and any
 * injected data context (intelligence mode) are all built within the bound
 * tenant. See AiService / IntelligenceModeContext.
 */
class AiConversation extends Model
{
    use HasTenant;
    use SoftDeletes;

    public const MODE_HELP = 'help';
    public const MODE_INTELLIGENCE = 'intelligence';

    /** The two assistant modes (CLAUDE.md — System Help + Business Intelligence). */
    public const MODES = [
        self::MODE_HELP,
        self::MODE_INTELLIGENCE,
    ];

    protected $fillable = [
        'tenant_id',
        'user_id',
        'mode',
        'title',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(TenantUser::class, 'user_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(AiMessage::class, 'conversation_id');
    }

    /**
     * The most recent message in the thread (for the conversation-list preview).
     */
    public function latestMessage(): HasOne
    {
        return $this->hasOne(AiMessage::class, 'conversation_id')->latestOfMany();
    }
}
