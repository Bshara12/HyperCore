<?php

use App\Domains\Subscription\DTOs\Rule\AuthorizeEventDTO;

test('it initializes with all properties correctly', function () {
  $dto = new AuthorizeEventDTO(
    userId: 42,
    projectId: 10,
    eventKey: 'user.upgrade_plan'
  );

  expect($dto->userId)->toBe(42)
    ->and($dto->projectId)->toBe(10)
    ->and($dto->eventKey)->toBe('user.upgrade_plan');
});

test('it handles null projectId for global events', function () {
  // حالة فحص حدث عام غير مرتبط بمشروع محدد
  $dto = new AuthorizeEventDTO(
    userId: 99,
    projectId: null,
    eventKey: 'system.global_event'
  );

  expect($dto->userId)->toBe(99)
    ->and($dto->projectId)->toBeNull()
    ->and($dto->eventKey)->toBe('system.global_event');
});
