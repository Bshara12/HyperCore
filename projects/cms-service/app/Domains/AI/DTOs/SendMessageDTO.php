<?php

namespace App\Domains\AI\DTOs;

use Illuminate\Http\Request;

class SendMessageDTO
{
  public const ACTION_CHAT      = 'chat';
  public const ACTION_PROVISION = 'provision';

  public function __construct(
    public readonly int     $userId,
    public readonly string  $content,
    public readonly ?int    $conversationId,
    public readonly string  $action,          // chat | provision
  ) {}

  public static function fromRequest(Request $request, int $userId): self
  {
    return new self(
      userId: $userId,
      content: $request['content'],
      conversationId: $request['conversation_id'] ?? null,
      action: $request['action']          ?? self::ACTION_CHAT,
    );
  }
}
