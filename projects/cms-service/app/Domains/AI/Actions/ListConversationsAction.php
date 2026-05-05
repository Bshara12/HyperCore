<?php

namespace App\Domains\AI\Actions;

use App\Domains\AI\Repositories\Interface\AiConversationRepositoryInterface;

class ListConversationsAction
{
  public function __construct(
    private AiConversationRepositoryInterface $repository,
  ) {}

  public function execute(int $userId, int $perPage = 15): array
  {
    $paginated = $this->repository->listConversations($userId, $perPage);

    return [
      'conversations' => collect($paginated->items())->map(fn($c) => [
        'id'                     => $c->id,
        'title'                  => $c->title ?? 'Untitled',
        'status'                 => $c->status,
        'provisioned_project_id' => $c->provisioned_project_id,
        'last_message'           => $c->lastMessage->first()
          ? [
            'role'       => $c->lastMessage->first()->role,
            'content'    => \Illuminate\Support\Str::limit($c->lastMessage->first()->content, 80),
            'created_at' => $c->lastMessage->first()->created_at,
          ]
          : null,
        'created_at'             => $c->created_at,
      ])->toArray(),
      'meta' => [
        'current_page' => $paginated->currentPage(),
        'last_page'    => $paginated->lastPage(),
        'total'        => $paginated->total(),
        'per_page'     => $paginated->perPage(),
      ],
    ];
  }
}
