<?php

use App\Domains\AI\Actions\GetConversationAction;
use App\Domains\AI\Repositories\Interface\AiConversationRepositoryInterface;
use App\Models\AiConversation;
use App\Models\AiMessage;

test('it returns formatted conversation and messages when found', function () {
  $repositoryMock = mock(AiConversationRepositoryInterface::class);

  // 1. إنشاء الموديل وتعيين القيم يدوياً
  $conversation = new AiConversation();
  $conversation->id = 1; // تعيين الـ id مباشرة
  $conversation->title = 'Test Conversation';
  $conversation->status = 'active';
  $conversation->provisioned_project_id = 100;
  $conversation->created_at = now();
  $conversation->updated_at = now();

  // 2. نفس الشيء للرسالة
  $message = new AiMessage();
  $message->id = 10;
  $message->role = 'user';
  $message->content = 'Hello AI';
  $message->schema = ['data' => 'test'];
  $message->is_provisioned = false;
  $message->sequence = 1;
  $message->created_at = now();

  $messages = collect([$message]);

  // ... باقي الاختبار كما هو (لن يتغير شيء)
  $repositoryMock->shouldReceive('findConversationForUser')
    ->once()
    ->with(1, 100)
    ->andReturn($conversation);

  $repositoryMock->shouldReceive('getMessages')
    ->once()
    ->with(1)
    ->andReturn($messages);

  $action = new GetConversationAction($repositoryMock);
  $result = $action->execute(1, 100);

  expect($result['conversation']['id'])->toBe(1)
    ->and($result['messages'][0]['role'])->toBe('user');
});

test('it throws exception when conversation is not found', function () {
  $repositoryMock = mock(AiConversationRepositoryInterface::class);

  $repositoryMock->shouldReceive('findConversationForUser')
    ->once()
    ->andReturn(null);

  $action = new GetConversationAction($repositoryMock);

  // التأكد من أن الـ Action يرمي استثناء 404
  $action->execute(999, 100);
})->throws(\Exception::class, 'Conversation not found.');
