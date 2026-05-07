<?php

namespace App\Domains\AI\Actions;

use App\Domains\AI\Repositories\Interface\AiConversationRepositoryInterface;

class DeleteConversationAction
{
  public function __construct(
    private AiConversationRepositoryInterface $repository,
  ) {}

  public function execute(int $conversationId, int $userId): void
  {
    $conversation = $this->repository->findConversationForUser(
      $conversationId,
      $userId
    );

    if (!$conversation) {
      throw new \Exception('Conversation not found.', 404);
    }

    $this->repository->deleteConversation($conversationId);
  }
}
