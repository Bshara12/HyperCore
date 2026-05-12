<?php

namespace Tests\Unit\Domains\Booking\Read\DTOs;

use App\Domains\Booking\Read\DTOs\GetResourceBookingsDTO;
use App\Domains\Booking\Requests\GetResourceBookingsRequest;

test('it can be created from request with all filters provided', function () {
  // 1. Arrange
  $resourceId = 15;
  $request = new GetResourceBookingsRequest([
    'status' => 'confirmed',
    'from'   => '2026-01-01',
    'to'     => '2026-01-31',
  ]);

  // 2. Act
  $dto = GetResourceBookingsDTO::fromRequest($resourceId, $request);

  // 3. Assert
  expect($dto->resourceId)->toBe($resourceId)
    ->and($dto->status)->toBe('confirmed')
    ->and($dto->from)->toBe('2026-01-01')
    ->and($dto->to)->toBe('2026-01-31');
});

test('it can be created with null filters if not provided in request', function () {
  // 1. Arrange
  $resourceId = 20;
  $request = new GetResourceBookingsRequest([]); // طلب فارغ

  // 2. Act
  $dto = GetResourceBookingsDTO::fromRequest($resourceId, $request);

  // 3. Assert
  expect($dto->resourceId)->toBe($resourceId)
    ->and($dto->status)->toBeNull()
    ->and($dto->from)->toBeNull()
    ->and($dto->to)->toBeNull();
});
