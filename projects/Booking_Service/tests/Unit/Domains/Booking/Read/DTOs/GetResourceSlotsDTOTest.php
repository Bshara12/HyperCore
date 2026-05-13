<?php

namespace Tests\Unit\Domains\Booking\Read\DTOs;

use App\Domains\Booking\Read\DTOs\GetResourceSlotsDTO;
use App\Domains\Booking\Requests\GetSlotsRequest;

test('it can be created from request with resource id and date', function () {
  // 1. Arrange
  $resourceId = 101;
  $date = '2026-05-20';
  $request = new GetSlotsRequest([
    'date' => $date,
  ]);

  // 2. Act
  $dto = GetResourceSlotsDTO::fromRequest($resourceId, $request);

  // 3. Assert
  expect($dto->resourceId)->toBe($resourceId)
    ->and($dto->date)->toBe($date);
});
