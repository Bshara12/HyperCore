<?php

namespace App\Domains\AI\Services;

use App\Domains\AI\Actions\DeleteConversationAction;
use App\Domains\AI\Actions\GetConversationAction;
use App\Domains\AI\Actions\ListConversationsAction;
use App\Domains\AI\Actions\SendAiMessageAction;
use App\Domains\AI\DTOs\SendMessageDTO;

class AiConversationService
{
  public function __construct(
    private SendAiMessageAction    $sendMessage,
    private GetConversationAction  $getConversation,
    private ListConversationsAction $listConversations,
    private DeleteConversationAction $deleteConversation,
  ) {}

  public function send(SendMessageDTO $dto): array
  {
    return $this->sendMessage->execute($dto);
  }

  public function get(int $conversationId, int $userId): array
  {
    return $this->getConversation->execute($conversationId, $userId);
  }

  public function list(int $userId, int $perPage = 15): array
  {
    return $this->listConversations->execute($userId, $perPage);
  }

  public function delete(int $conversationId, int $userId): void
  {
    $this->deleteConversation->execute($conversationId, $userId);
  }
}
