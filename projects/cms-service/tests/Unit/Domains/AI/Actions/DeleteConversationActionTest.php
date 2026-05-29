<?php

namespace Tests\Unit\Domains\AI\Actions;

use App\Domains\AI\Actions\DeleteConversationAction;
use App\Domains\AI\Repositories\Interface\AiConversationRepositoryInterface;
use App\Models\AiConversation; // افترضت وجود هذا الموديل
use Mockery;

test('it deletes conversation successfully when found', function () {
  // 1. إنشاء Mock للـ Repository
  $repositoryMock = Mockery::mock(AiConversationRepositoryInterface::class);

  // 2. محاكاة وجود المحادثة
  $conversation = new AiConversation(['id' => 1]);
  $repositoryMock->shouldReceive('findConversationForUser')
    ->once()
    ->with(1, 100)
    ->andReturn($conversation);

  // 3. التأكد من استدعاء دالة الحذف
  $repositoryMock->shouldReceive('deleteConversation')
    ->once()
    ->with(1);

  // 4. تنفيذ الـ Action
  $action = new DeleteConversationAction($repositoryMock);
  $action->execute(1, 100);

  // لا حاجة لـ assertDatabaseHas لأننا نختبر "المنطق" فقط
  expect(true)->toBeTrue();
});

test('it throws exception when conversation not found', function () {
  // 1. إنشاء Mock للـ Repository
  $repositoryMock = Mockery::mock(AiConversationRepositoryInterface::class);

  // 2. محاكاة عدم وجود المحادثة
  $repositoryMock->shouldReceive('findConversationForUser')
    ->once()
    ->with(1, 100)
    ->andReturn(null);

  // 3. تنفيذ الـ Action وتوقع الخطأ
  $action = new DeleteConversationAction($repositoryMock);

  $action->execute(1, 100);
})->throws(\Exception::class, 'Conversation not found.');
