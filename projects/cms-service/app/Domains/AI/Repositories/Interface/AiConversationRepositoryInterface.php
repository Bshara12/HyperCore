<?php

namespace App\Domains\AI\Repositories\Interface;

use App\Models\AiConversation;
use Illuminate\Support\Collection;

interface AiConversationRepositoryInterface
{
  // ─── Conversations ────────────────────────────────────────
  public function createConversation(int $userId, string $title): AiConversation;

  public function findConversation(int $conversationId): ?AiConversation;

  public function findConversationForUser(int $conversationId, int $userId): ?AiConversation;

  public function listConversations(int $userId, int $perPage): \Illuminate\Pagination\LengthAwarePaginator;

  public function updateConversationTitle(int $conversationId, string $title): void;

  public function updateConversationStatus(int $conversationId, string $status): void;

  public function markAsProvisioned(int $conversationId, int $projectId): void;

  public function deleteConversation(int $conversationId): void;

  // ─── Messages ─────────────────────────────────────────────
  public function addMessage(
    int     $conversationId,
    string  $role,
    string  $content,
    ?array  $schema,
    int     $sequence,
  ): \App\Models\AiMessage;

  public function getMessages(int $conversationId): Collection;

  public function getLastSequence(int $conversationId): int;

  public function markMessageAsProvisioned(int $messageId): void;
}
