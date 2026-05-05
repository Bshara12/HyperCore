<?php

namespace App\Domains\AI\Actions;

use App\Domains\AI\Repositories\Interface\AiConversationRepositoryInterface;

class GetConversationAction
{
  public function __construct(
    private AiConversationRepositoryInterface $repository,
  ) {}

  public function execute(int $conversationId, int $userId): array
  {
    $conversation = $this->repository->findConversationForUser(
      $conversationId,
      $userId
    );

    if (!$conversation) {
      throw new \Exception('Conversation not found.', 404);
    }

    $messages = $this->repository->getMessages($conversationId);

    return [
      'conversation' => [
        'id'                     => $conversation->id,
        'title'                  => $conversation->title,
        'status'                 => $conversation->status,
        'provisioned_project_id' => $conversation->provisioned_project_id,
        'created_at'             => $conversation->created_at,
        'updated_at'             => $conversation->updated_at,
      ],
      'messages' => $messages->map(fn($m) => [
        'id'             => $m->id,
        'role'           => $m->role,
        'content'        => $m->content,
        'schema'         => $m->schema,
        'is_provisioned' => $m->is_provisioned,
        'sequence'       => $m->sequence,
        'created_at'     => $m->created_at,
      ])->values()->toArray(),
      'total_messages' => $messages->count(),
    ];
  }
}
