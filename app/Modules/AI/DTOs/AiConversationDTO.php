<?php

namespace App\Modules\AI\DTOs;

use App\DTOs\BaseDTO;
use Illuminate\Http\Request;

/**
 * Carries a validated chat turn into AiService::chat().
 *
 * conversation_id null = start a new conversation (mode decides the assistant
 * context). For an existing conversation the stored mode is authoritative, so
 * `mode` here is only consulted when creating a new thread.
 */
class AiConversationDTO extends BaseDTO
{
    public function __construct(
        public readonly ?int $conversation_id,
        public readonly string $mode,
        public readonly string $message,
    ) {}

    public static function fromRequest(Request $request): self
    {
        return new self(
            conversation_id: $request->filled('conversation_id')
                ? (int) $request->integer('conversation_id')
                : null,
            mode: $request->string('mode')->toString(),
            message: $request->string('message')->toString(),
        );
    }
}
