<?php

use App\Domains\E_Commerce\DTOs\Wishlist\ReorderWishlistItemsDTO;

it('creates a reorder dto from array correctly', function () {
  // 1. Arrange
  $items = [
    ['wishlist_item_id' => 1, 'order' => 1],
    ['wishlist_item_id' => 2, 'order' => 2],
  ];

  $data = [
    'wishlist_id' => '50', // نص للتأكد من الـ casting
    'items' => $items
  ];

  // 2. Act
  $dto = ReorderWishlistItemsDTO::fromArray($data);

  // 3. Assert
  expect($dto)->toBeInstanceOf(ReorderWishlistItemsDTO::class)
    ->and($dto->wishlist_id)->toBe(50)
    ->and($dto->items)->toBeArray()
    ->and($dto->items)->toEqual($items);
});

it('transforms reorder dto to array correctly', function () {
  // 1. Arrange
  $items = [
    ['wishlist_item_id' => 10, 'order' => 0],
  ];
  $dto = new ReorderWishlistItemsDTO(wishlist_id: 5, items: $items);

  // 2. Act
  $result = $dto->toArray();

  // 3. Assert
  expect($result)->toBeArray()
    ->and($result['wishlist_id'])->toBe(5)
    ->and($result['items'])->toBe($items);
});
