<?php

use App\Domains\E_Commerce\DTOs\Wishlist\MoveWishlistItemToCartDTO;

it('creates a move item dto from array with integer casting correctly', function () {
  // 1. Arrange: إرسال البيانات كقيم نصية للتأكد من تحويلها لأرقام
  $data = [
    'wishlist_id' => '101',
    'wishlist_item_id' => '500',
    'project_id' => '1',
    'user_id' => '99'
  ];

  // 2. Act
  $dto = MoveWishlistItemToCartDTO::fromArray($data);

  // 3. Assert
  expect($dto)->toBeInstanceOf(MoveWishlistItemToCartDTO::class)
    ->and($dto->wishlist_id)->toBe(101)
    ->and($dto->wishlist_item_id)->toBe(500)
    ->and($dto->project_id)->toBe(1)
    ->and($dto->user_id)->toBe(99);
});

it('converts the move item dto to array correctly', function () {
  // اختبار دالة toArray
  $dto = new MoveWishlistItemToCartDTO(
    wishlist_id: 1,
    wishlist_item_id: 2,
    project_id: 3,
    user_id: 4
  );

  $array = $dto->toArray();

  expect($array)->toBeArray()
    ->and($array)->toEqual([
      'wishlist_id' => 1,
      'wishlist_item_id' => 2,
      'project_id' => 3,
      'user_id' => 4,
    ]);
});
