<?php

namespace App\Modules\AI\Events;

use App\Modules\AI\Models\AiMessage;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired after the assistant reply is persisted for a chat turn.
 *
 * No listeners yet — reserved for future usage tracking (per-tenant token
 * accounting / cost reporting). Carries the assistant AiMessage, from which the
 * conversation, tenant, provider and token count are reachable. Because it has
 * no listeners, it is NOT registered in EventServiceProvider (dispatched
 * directly from AiService), matching the codebase convention for listener-less
 * events.
 */
class AiQueryProcessed
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly AiMessage $message,
    ) {}
}
