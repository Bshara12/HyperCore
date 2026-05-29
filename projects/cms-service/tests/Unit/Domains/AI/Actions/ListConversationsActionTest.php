<?php

use App\Domains\AI\Actions\ListConversationsAction;
use App\Domains\AI\Repositories\Interface\AiConversationRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

test('it lists conversations with pagination and correct mapping', function () {
  $repositoryMock = mock(AiConversationRepositoryInterface::class);

  // 1. تحضير بيانات وهمية (Conversation واحدة على الأقل)
  $conversation = new \stdClass();
  $conversation->id = 1;
  $conversation->title = 'AI Chat';
  $conversation->status = 'active';
  $conversation->provisioned_project_id = 10;
  $conversation->created_at = now();

  // محاكاة العلاقة lastMessage
  $lastMessage = new \stdClass();
  $lastMessage->role = 'assistant';
  $lastMessage->content = 'This is a very long message that should be limited by the Str::limit logic in the action.';
  $lastMessage->created_at = now();

  $conversation->lastMessage = collect([$lastMessage]);

  // 2. إنشاء Paginator حقيقي (هذا أسهل بكثير من Mocking الـ Paginator)
  $paginated = new LengthAwarePaginator(
    [$conversation], // Items
    1,               // Total
    15,              // Per Page
    1                // Current Page
  );

  // 3. التوقعات
  $repositoryMock->shouldReceive('listConversations')
    ->once()
    ->with(100, 15)
    ->andReturn($paginated);

  // 4. التنفيذ
  $action = new ListConversationsAction($repositoryMock);
  $result = $action->execute(100, 15);

  // 5. التحقق (Assertions)
  expect($result['conversations'])->toBeArray()->toHaveCount(1)
    ->and($result['conversations'][0]['id'])->toBe(1)
    ->and($result['conversations'][0]['last_message']['content'])->toContain('...') // للتأكد من عمل Str::limit
    ->and($result['meta']['total'])->toBe(1)
    ->and($result['meta']['current_page'])->toBe(1);
});

test('it returns null for last_message when conversation has no messages', function () {
  $repositoryMock = mock(AiConversationRepositoryInterface::class);

  $conversation = new \stdClass();
  $conversation->id = 1;
  $conversation->title = 'Empty Chat';
  $conversation->status = 'active';
  $conversation->provisioned_project_id = null;
  $conversation->created_at = now();
  // محاكاة عدم وجود رسائل
  $conversation->lastMessage = collect([]);

  $paginated = new LengthAwarePaginator([$conversation], 1, 15, 1);

  $repositoryMock->shouldReceive('listConversations')->andReturn($paginated);

  $action = new ListConversationsAction($repositoryMock);
  $result = $action->execute(100, 15);

  expect($result['conversations'][0]['last_message'])->toBeNull();
});
