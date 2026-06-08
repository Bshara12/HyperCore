<?php

use App\Domains\Auth\Action\CheckProjectAccessAction;
use App\Domains\Auth\DTOs\CheckProjectAccessDto;
use App\Domains\Auth\Repository\Interface\ProjectUserRepositoryInterface;

test('it verifies project access using repository', function () {
  // 1. Arrange: تجهيز الـ Mock للـ Repository
  $repoMock = mock(ProjectUserRepositoryInterface::class);

  // نتوقع استدعاء دالة exists مع القيم المرسلة في الـ DTO
  $repoMock->shouldReceive('exists')
    ->once()
    ->with(1, 'valid-key')
    ->andReturn(true); // نفترض أن الوصول مسموح

  // 2. Act: إنشاء الـ Action وحقن الـ Mock
  $action = new CheckProjectAccessAction($repoMock);
  $dto = new CheckProjectAccessDto(1, 'valid-key');

  // 3. Assert: التأكد من النتيجة
  $result = $action->execute($dto);

  expect($result)->toBeTrue();
});

test('it returns false when access does not exist', function () {
  // حالة الفشل
  $repoMock = mock(ProjectUserRepositoryInterface::class);

  $repoMock->shouldReceive('exists')
    ->once()
    ->with(1, 'invalid-key')
    ->andReturn(false);

  $action = new CheckProjectAccessAction($repoMock);
  $dto = new CheckProjectAccessDto(1, 'invalid-key');

  $result = $action->execute($dto);

  expect($result)->toBeFalse();
});
