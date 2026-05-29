<?php

namespace Tests\Unit\Domains\AI\Services;

use App\Domains\AI\Actions\DeleteConversationAction;
use App\Domains\AI\Actions\GetConversationAction;
use App\Domains\AI\Actions\ListConversationsAction;
use App\Domains\AI\Actions\SendAiMessageAction;
use App\Domains\AI\DTOs\SendMessageDTO;
use App\Domains\AI\Services\AiConversationService;
use Mockery;

// سنقوم بإنشاء Mocks للأكشنز الأربعة
beforeEach(function () {
  $this->sendAction = Mockery::mock(SendAiMessageAction::class);
  $this->getAction = Mockery::mock(GetConversationAction::class);
  $this->listAction = Mockery::mock(ListConversationsAction::class);
  $this->deleteAction = Mockery::mock(DeleteConversationAction::class);

  $this->service = new AiConversationService(
    $this->sendAction,
    $this->getAction,
    $this->listAction,
    $this->deleteAction
  );
});

test('it calls send action', function () {
  $dto = new SendMessageDTO(1, 'Hello', null, 'chat');
  $this->sendAction->shouldReceive('execute')->once()->with($dto)->andReturn(['status' => 'ok']);

  $result = $this->service->send($dto);
  expect($result)->toBe(['status' => 'ok']);
});

test('it calls get action', function () {
  $this->getAction->shouldReceive('execute')->once()->with(123, 1)->andReturn(['id' => 123]);

  $result = $this->service->get(123, 1);
  expect($result)->toBe(['id' => 123]);
});

test('it calls list action', function () {
  $this->listAction->shouldReceive('execute')->once()->with(1, 15)->andReturn(['conv1', 'conv2']);

  $result = $this->service->list(1, 15);
  expect($result)->toBe(['conv1', 'conv2']);
});

test('it calls delete action', function () {
  $this->deleteAction->shouldReceive('execute')->once()->with(123, 1);

  $this->service->delete(123, 1);
  // إذا لم يرمِ خطأ، فالاختبار ناجح
  expect(true)->toBeTrue();
});
