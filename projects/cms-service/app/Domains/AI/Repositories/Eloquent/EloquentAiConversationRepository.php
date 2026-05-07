<?php

namespace App\Domains\AI\Repositories\Eloquent;

use App\Domains\AI\Repositories\Interface\AiConversationRepositoryInterface;
use App\Models\AiConversation;
use App\Models\AiMessage;
use Illuminate\Support\Collection;

class EloquentAiConversationRepository implements AiConversationRepositoryInterface
{
  // ─── Conversations ────────────────────────────────────────

  public function createConversation(int $userId, string $title): AiConversation
  {
    return AiConversation::create([
      'user_id' => $userId,
      'title'   => $title,
      'status'  => 'active',
    ]);
  }

  public function findConversation(int $conversationId): ?AiConversation
  {
    return AiConversation::find($conversationId);
  }

  public function findConversationForUser(int $conversationId, int $userId): ?AiConversation
  {
    return AiConversation::where('id', $conversationId)
      ->where('user_id', $userId)
      ->first();
  }

  public function listConversations(int $userId, int $perPage): \Illuminate\Pagination\LengthAwarePaginator
  {
    return AiConversation::where('user_id', $userId)
      ->where('status', 'active')
      ->with(['lastMessage'])
      ->latest()
      ->paginate($perPage);
  }

  public function updateConversationTitle(int $conversationId, string $title): void
  {
    AiConversation::where('id', $conversationId)->update(['title' => $title]);
  }

  public function updateConversationStatus(int $conversationId, string $status): void
  {
    AiConversation::where('id', $conversationId)->update(['status' => $status]);
  }

  public function markAsProvisioned(int $conversationId, int $projectId): void
  {
    AiConversation::where('id', $conversationId)->update([
      'provisioned_project_id' => $projectId,
    ]);
  }

  public function deleteConversation(int $conversationId): void
  {
    AiConversation::where('id', $conversationId)->delete();
  }

  // ─── Messages ─────────────────────────────────────────────

  public function addMessage(
    int    $conversationId,
    string $role,
    string $content,
    ?array $schema,
    int    $sequence,
  ): AiMessage {
    return AiMessage::create([
      'conversation_id' => $conversationId,
      'role'            => $role,
      'content'         => $content,
      'schema'          => $schema,
      'is_provisioned'  => false,
      'sequence'        => $sequence,
      'created_at'      => now(),
    ]);
  }

  public function getMessages(int $conversationId): Collection
  {
    return AiMessage::where('conversation_id', $conversationId)
      ->orderBy('sequence')
      ->get();
  }

  public function getLastSequence(int $conversationId): int
  {
    return AiMessage::where('conversation_id', $conversationId)
      ->max('sequence') ?? 0;
  }

  public function markMessageAsProvisioned(int $messageId): void
  {
    AiMessage::where('id', $messageId)->update(['is_provisioned' => true]);
  }
}
