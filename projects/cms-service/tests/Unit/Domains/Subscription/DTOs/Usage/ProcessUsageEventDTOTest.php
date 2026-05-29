<?php

use App\Domains\Subscription\DTOs\Usage\ProcessUsageEventDTO;

test('it initializes with all properties correctly', function () {
  $dto = new ProcessUsageEventDTO(
    userId: 1,
    projectId: 10,
    eventKey: 'api.usage_count',
    amount: 5
  );

  expect($dto->userId)->toBe(1)
    ->and($dto->projectId)->toBe(10)
    ->and($dto->eventKey)->toBe('api.usage_count')
    ->and($dto->amount)->toBe(5);
});

test('it uses default amount of 1 when not provided', function () {
  $dto = new ProcessUsageEventDTO(
    userId: 2,
    projectId: null,
    eventKey: 'storage.upload'
  );

  expect($dto->userId)->toBe(2)
    ->and($dto->projectId)->toBeNull()
    ->and($dto->amount)->toBe(1);
});
