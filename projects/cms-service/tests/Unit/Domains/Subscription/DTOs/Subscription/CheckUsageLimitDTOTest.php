<?php

use App\Domains\Subscription\DTOs\Subscription\CheckUsageLimitDTO;

test('it initializes with all properties correctly', function () {
  $dto = new CheckUsageLimitDTO(
    userId: 1,
    projectId: 50,
    featureKey: 'api.requests',
    requestedAmount: 5
  );

  expect($dto->userId)->toBe(1)
    ->and($dto->projectId)->toBe(50)
    ->and($dto->featureKey)->toBe('api.requests')
    ->and($dto->requestedAmount)->toBe(5);
});

test('it uses default value for requestedAmount when not provided', function () {
  $dto = new CheckUsageLimitDTO(
    userId: 1,
    projectId: null,
    featureKey: 'storage.upload'
  );

  // التحقق من أن القيمة الافتراضية هي 1
  expect($dto->requestedAmount)->toBe(1)
    ->and($dto->projectId)->toBeNull();
});
