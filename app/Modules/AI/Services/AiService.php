<?php

namespace App\Modules\AI\Services;

use App\Modules\AI\AiProviderFactory;
use App\Modules\AI\Context\HelpModeContext;
use App\Modules\AI\Context\IntelligenceModeContext;
use App\Modules\AI\DTOs\AiConversationDTO;
use App\Modules\AI\Events\AiQueryProcessed;
use App\Modules\AI\Models\AiConversation;
use App\Modules\AI\Models\AiMessage;
use App\Modules\AI\Providers\AbstractAiProvider;
use App\Modules\SaasCore\Models\Tenant;
use App\Services\BaseService;
use Illuminate\Support\Str;

/**
 * Orchestrates an AI chat turn. ALL operations are scoped to the bound tenant
 * (TenantScope on AiConversation/AiMessage) and the current tenant user — the
 * assistant never reads or returns another tenant's data.
 *
 * Flow: resolve/create the conversation → persist the user message FIRST (so a
 * provider failure can never lose it) → build a bounded history window → build
 * the mode's system prompt (intelligence mode injects a fresh tenant data
 * snapshot) → ask the provider → persist the assistant reply with the provider
 * that actually answered + tokens used → fire AiQueryProcessed.
 *
 * The provider call is intentionally NOT wrapped in a DB transaction: it is a
 * synchronous outbound HTTP request (AI is sync this session), and the contract
 * guarantees it never throws, so there is nothing to roll back and no reason to
 * hold a transaction open across the network round-trip.
 */
class AiService extends BaseService
{
    /** Context-window cap: the last N messages sent to the provider. */
    private const HISTORY_LIMIT = 10;

    public function __construct(
        private readonly AiProviderFactory $factory,
        private readonly HelpModeContext $helpContext,
        private readonly IntelligenceModeContext $intelligenceContext,
    ) {}

    public function chat(AiConversationDTO $dto): AiMessage
    {
        /** @var Tenant $tenant */
        $tenant = app('current_tenant');
        $userId = auth('tenant')->id();

        $conversation = $this->resolveConversation($dto, $userId);

        // Derive the thread title from the first message (covers both creation
        // paths: a brand-new thread here, or an empty one from startConversation).
        if ($conversation->title === null) {
            $conversation->update(['title' => Str::limit(trim($dto->message), 60, '…')]);
        }

        // Persist the user message before calling out — it must survive a
        // provider error (which returns an error string, never throws).
        $conversation->messages()->create([
            'tenant_id' => $tenant->id,
            'role' => AiMessage::ROLE_USER,
            'content' => $dto->message,
        ]);

        // Bounded context window: last HISTORY_LIMIT messages, oldest → newest,
        // including the message just saved. Tenant-scoped via the relation.
        $history = $conversation->messages()
            ->orderByDesc('id')
            ->limit(self::HISTORY_LIMIT)
            ->get()
            ->sortBy('id')
            ->map(fn (AiMessage $m): array => ['role' => $m->role, 'content' => $m->content])
            ->values()
            ->all();

        // Intelligence mode builds a FRESH tenant data snapshot every call.
        $systemPrompt = $conversation->mode === AiConversation::MODE_INTELLIGENCE
            ? $this->intelligenceContext->systemPrompt($tenant)
            : $this->helpContext->systemPrompt();

        $provider = $this->factory->make($tenant);
        $reply = $provider->query($history, $systemPrompt, (int) $tenant->id);

        $assistantMessage = $conversation->messages()->create([
            'tenant_id' => $tenant->id,
            'role' => AiMessage::ROLE_ASSISTANT,
            'content' => $reply,
            // Record the provider that ACTUALLY answered (may be 'log' when the
            // selected provider's key is absent and the factory fell back).
            'provider' => $provider instanceof AbstractAiProvider ? $provider->name() : 'log',
            'tokens_used' => $provider instanceof AbstractAiProvider ? $provider->lastTokensUsed() : null,
        ]);

        AiQueryProcessed::dispatch($assistantMessage);

        return $assistantMessage;
    }

    /**
     * Start an empty conversation in the given mode for the current tenant user.
     * Mode is fixed for the conversation's life; the title is filled from the
     * first message on the initial chat() turn. tenant_id is auto-filled by
     * HasTenant from the bound tenant.
     */
    public function startConversation(string $mode, ?int $userId): AiConversation
    {
        return AiConversation::create([
            'user_id' => $userId,
            'mode' => $mode,
        ]);
    }

    /**
     * Resolve the target conversation: an existing one (already authorized for
     * ownership by the controller, and tenant-scoped on lookup), or a new thread
     * whose mode is fixed at creation (title filled on the first message above).
     */
    private function resolveConversation(AiConversationDTO $dto, ?int $userId): AiConversation
    {
        if ($dto->conversation_id !== null) {
            return AiConversation::findOrFail($dto->conversation_id);
        }

        return AiConversation::create([
            'user_id' => $userId,
            'mode' => $dto->mode,
        ]);
    }
}
