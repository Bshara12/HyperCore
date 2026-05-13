<?php

use App\Domains\E_Commerce\DTOs\Wishlist\AddWishlistItemDTO;

it('creates a dto from an array with all values correctly', function () {
  // 1. Arrange
  $data = [
    'wishlist_id' => "10", // نرسله كنص للتأكد من نجاح الـ (int) casting
    'product_id' => 55,
    'variant_id' => 101
  ];

  // 2. Act
  $dto = AddWishlistItemDTO::fromArray($data);

  // 3. Assert
  expect($dto)->toBeInstanceOf(AddWishlistItemDTO::class)
    ->and($dto->wishlist_id)->toBe(10)
    ->and($dto->product_id)->toBe(55)
    ->and($dto->variant_id)->toBe(101);
});

it('creates a dto from an array without variant_id correctly', function () {
  // اختبار الحالة الشرطية عندما يكون variant_id غير موجود
  $data = [
    'wishlist_id' => 1,
    'product_id' => 2
    // variant_id missing
  ];

  $dto = AddWishlistItemDTO::fromArray($data);

  expect($dto->variant_id)->toBeNull();
});

it('converts the dto back to an array correctly', function () {
  // اختبار دالة toArray
  $dto = new AddWishlistItemDTO(wishlist_id: 1, product_id: 2, variant_id: 3);

  $array = $dto->toArray();

  expect($array)->toBeArray()
    ->and($array)->toEqual([
      'wishlist_id' => 1,
      'product_id' => 2,
      'variant_id' => 3,
    ]);
});

it('can be instantiated via constructor with default values', function () {
  // التأكد من أن القيمة الافتراضية للـ variant_id هي null
  $dto = new AddWishlistItemDTO(1, 2);

  expect($dto->variant_id)->toBeNull();
});
