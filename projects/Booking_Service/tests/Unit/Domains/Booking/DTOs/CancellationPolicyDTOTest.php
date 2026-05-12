<?php

use App\Domains\Booking\DTOs\CancellationPolicyDTO;

test('it can be instantiated with correct data', function () {
  // 1. Arrange
  $resourceId = 10;
  $hoursBefore = 24;
  $refundPercentage = 50;
  $description = 'Full refund if cancelled 24h before';

  // 2. Act
  $dto = new CancellationPolicyDTO(
    resourceId: $resourceId,
    hoursBefore: $hoursBefore,
    refundPercentage: $refundPercentage,
    description: $description
  );

  // 3. Assert
  expect($dto->resourceId)->toBe($resourceId)
    ->and($dto->hoursBefore)->toBe($hoursBefore)
    ->and($dto->refundPercentage)->toBe($refundPercentage)
    ->and($dto->description)->toBe($description);
});

test('it can be created from an array via fromArray method', function () {
  // 1. Arrange
  $resourceId = 5;
  $data = [
    'hours_before' => 48,
    'refund_percentage' => 100,
    'description' => 'Cancel early for a full refund'
  ];

  // 2. Act
  $dto = CancellationPolicyDTO::fromArray($data, $resourceId);

  // 3. Assert
  expect($dto)->toBeInstanceOf(CancellationPolicyDTO::class)
    ->and($dto->resourceId)->toBe($resourceId)
    ->and($dto->hoursBefore)->toBe(48)
    ->and($dto->refundPercentage)->toBe(100)
    ->and($dto->description)->toBe('Cancel early for a full refund');
});

test('it sets description to null if missing in array', function () {
  // 1. Arrange
  $resourceId = 1;
  $data = [
    'hours_before' => 12,
    'refund_percentage' => 25,
    // description missing
  ];

  // 2. Act
  $dto = CancellationPolicyDTO::fromArray($data, $resourceId);

  // 3. Assert
  expect($dto->description)->toBeNull();
});
