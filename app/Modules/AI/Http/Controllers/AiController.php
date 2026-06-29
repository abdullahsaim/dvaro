<?php

namespace App\Modules\AI\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\AI\DTOs\AiConversationDTO;
use App\Modules\AI\Http\Requests\AiChatRequest;
use App\Modules\AI\Models\AiConversation;
use App\Modules\AI\Services\AiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * AI Assistant (tenant app). Thin controller: validate → Service → respond.
 *
 * ──────────────────────────────────────────────────────────────────────────
 * AUTHORIZATION — READ BEFORE EDITING (same rule as every tenant module):
 * Every action MUST authorize via:
 *
 *     Gate::forUser(auth('tenant')->user())->authorize($ability, $target);
 *
 * Do NOT use $this->authorize(): it resolves the DEFAULT (web) guard, which is
 * empty here — tenant users live on the 'tenant' guard.
 *
 * Conversations are scoped to the current tenant AND the current user: AiPolicy
 * enforces own-conversation ownership, so one staff member can never read
 * another's chats. The whole group is also gated by 'tenant.module:ai' (the plan
 * must include the AI module) — see routes/tenant.php.
 * ──────────────────────────────────────────────────────────────────────────
 */
class AiController extends Controller
{
    public function index(): Response
    {
        Gate::forUser(auth('tenant')->user())->authorize('viewAny', AiConversation::class);

        $conversations = AiConversation::query()
            ->where('user_id', auth('tenant')->id())
            ->with(['latestMessage:id,conversation_id,role,content,created_at'])
            ->latest()
            ->get(['id', 'mode', 'title', 'created_at']);

        return Inertia::render('AI/Index', [
            'conversations' => $conversations,
            'modes' => AiConversation::MODES,
        ]);
    }

    public function show(AiConversation $conversation): Response
    {
        Gate::forUser(auth('tenant')->user())->authorize('view', $conversation);

        $conversation->load([
            'messages' => fn ($q) => $q->orderBy('id'),
        ]);

        return Inertia::render('AI/Show', [
            'conversation' => [
                'id' => $conversation->id,
                'mode' => $conversation->mode,
                'title' => $conversation->title,
            ],
            'messages' => $conversation->messages->map(fn ($m) => [
                'id' => $m->id,
                'role' => $m->role,
                'content' => $m->content,
                'provider' => $m->provider,
                'created_at' => $m->created_at?->toISOString(),
            ]),
        ]);
    }

    /**
     * Process a chat turn. Returns JSON (NOT Inertia): the chat is async on the
     * client (axios), updating the thread without a full page reload.
     */
    public function chat(AiChatRequest $request, AiService $service): JsonResponse
    {
        $dto = AiConversationDTO::fromRequest($request);

        // Authorize against the target: an existing conversation (own-ownership)
        // or, for a new thread, the create ability.
        if ($dto->conversation_id !== null) {
            $conversation = AiConversation::findOrFail($dto->conversation_id);
            Gate::forUser(auth('tenant')->user())->authorize('chat', $conversation);
        } else {
            Gate::forUser(auth('tenant')->user())->authorize('create', AiConversation::class);
        }

        $message = $service->chat($dto);

        return response()->json([
            'conversation_id' => $message->conversation_id,
            'message' => [
                'id' => $message->id,
                'role' => $message->role,
                'content' => $message->content,
                'provider' => $message->provider,
                'created_at' => $message->created_at?->toISOString(),
            ],
        ]);
    }

    /**
     * Start a new (empty) conversation in the chosen mode, then redirect to it.
     * Mode is locked for the conversation's life.
     */
    public function newConversation(Request $request, AiService $service): RedirectResponse
    {
        Gate::forUser(auth('tenant')->user())->authorize('create', AiConversation::class);

        $validated = $request->validate([
            'mode' => ['required', Rule::in(AiConversation::MODES)],
        ]);

        $conversation = $service->startConversation($validated['mode'], auth('tenant')->id());

        return redirect()->route('tenant.ai.show', [
            'tenant_slug' => app('current_tenant')->slug,
            'conversation' => $conversation->id,
        ]);
    }

    public function destroy(AiConversation $conversation): RedirectResponse
    {
        Gate::forUser(auth('tenant')->user())->authorize('delete', $conversation);

        $conversation->delete(); // soft delete

        return redirect()
            ->route('tenant.ai.index', ['tenant_slug' => app('current_tenant')->slug])
            ->with('success', __('common.ai.conversation_deleted'));
    }
}
