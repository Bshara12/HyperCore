<?php

use App\Domains\E_Commerce\DTOs\Wishlist\CreateWishlistDTO;

it('creates a wishlist dto from array with full data correctly', function () {
  // 1. Arrange
  $data = [
    'user_id' => 1,
    'guest_token' => 'token_123',
    'name' => 'My Favorites',
    'visibility' => 'public',
    'is_default' => 1, // سنرسله كـ int للتأكد من الـ casting
    'is_shareable' => true,
  ];

  // 2. Act
  $dto = CreateWishlistDTO::fromArray($data);

  // 3. Assert
  expect($dto->name)->toBe('My Favorites')
    ->and($dto->user_id)->toBe(1)
    ->and($dto->guest_token)->toBe('token_123')
    ->and($dto->visibility)->toBe('public')
    ->and($dto->is_default)->toBeTrue()
    ->and($dto->is_shareable)->toBeTrue();
});

it('applies default values when optional fields are missing', function () {
  // اختبار الحالة الدنيا (Minimal data)
  $data = [
    'name' => 'Default List'
  ];

  $dto = CreateWishlistDTO::fromArray($data);

  expect($dto->user_id)->toBeNull()
    ->and($dto->guest_token)->toBeNull()
    ->and($dto->visibility)->toBe('private')
    ->and($dto->is_default)->toBeFalse()
    ->and($dto->is_shareable)->toBeFalse();
});

it('can transform dto to array accurately', function () {
  $dto = new CreateWishlistDTO(
    user_id: 10,
    guest_token: null,
    name: 'Test Array',
    visibility: 'private',
    is_default: true,
    is_shareable: false
  );

  $result = $dto->toArray();

  expect($result)->toBeArray()
    ->and($result['user_id'])->toBe(10)
    ->and($result['is_default'])->toBeTrue()
    ->and($result['guest_token'])->toBeNull();
});
