<?php

namespace Tests\Unit\Domains\Auth\Services;

use App\Domains\Auth\Action\CheckProjectAccessAction;
use App\Domains\Auth\DTOs\CheckProjectAccessDto;
use App\Domains\Auth\Service\ProjectAccessService;

test('it calls check action and returns its result', function () {
  // 1. Arrange: إنشاء Mock للـ Action
  $actionMock = mock(CheckProjectAccessAction::class);
  $dto = new CheckProjectAccessDto(1, 'project-key');

  // التأكد من أن الـ Action سيتم استدعاؤه بـ DTO الصحيح
  // وإرجاع قيمة (true في حالة السماح، false في حالة الرفض)
  $actionMock->shouldReceive('execute')
    ->once()
    ->with($dto)
    ->andReturn(true);

  // 2. Act: حقن الـ Mock في الخدمة
  $service = new ProjectAccessService($actionMock);

  // 3. Assert: التأكد من أن النتيجة تطابق ما أرجعه الـ Action
  expect($service->check($dto))->toBeTrue();
});

test('it returns false when action denies access', function () {
  $actionMock = mock(CheckProjectAccessAction::class);
  $dto = new CheckProjectAccessDto(1, 'project-key');

  $actionMock->shouldReceive('execute')
    ->once()
    ->with($dto)
    ->andReturn(false);

  $service = new ProjectAccessService($actionMock);

  expect($service->check($dto))->toBeFalse();
});
