<?php

use App\Domains\Booking\DTOs\AvailabilityDTO;

it('can be instantiated via constructor', function () {
  // 1. Arrange & Act
  $dto = new AvailabilityDTO(
    resourceId: 1,
    dayOfWeek: 1,
    startTime: '08:00',
    endTime: '17:00',
    slotDuration: 30,
    isActive: true
  );

  // 2. Assert
  expect($dto->resourceId)->toBe(1)
    ->and($dto->startTime)->toBe('08:00')
    ->and($dto->slotDuration)->toBe(30)
    ->and($dto->isActive)->toBeTrue();
});

it('can be created from an array using fromArray method', function () {
  // 1. Arrange
  $resourceId = 10;
  $data = [
    'day_of_week' => 5,
    'start_time' => '10:00',
    'end_time' => '18:00',
    'slot_duration' => 60,
    'is_active' => false,
  ];

  // 2. Act
  $dto = AvailabilityDTO::fromArray($data, $resourceId);

  // 3. Assert
  expect($dto)->toBeInstanceOf(AvailabilityDTO::class)
    ->and($dto->resourceId)->toBe(10)
    ->and($dto->dayOfWeek)->toBe(5)
    ->and($dto->startTime)->toBe('10:00')
    ->and($dto->isActive)->toBeFalse();
});

it('uses default value for isActive when not provided in array', function () {
  // 1. Arrange
  $data = [
    'day_of_week' => 2,
    'start_time' => '09:00',
    'end_time' => '15:00',
    'slot_duration' => 15,
    // 'is_active' missing
  ];

  // 2. Act
  $dto = AvailabilityDTO::fromArray($data, 1);

  // 3. Assert
  expect($dto->isActive)->toBeTrue();
});
