<?php

use App\Domains\E_Commerce\DTOs\Wishlist\WishlistItemDTO;
use App\Models\WishlistItem;
use Carbon\Carbon;

it('transforms a wishlist item model into dto correctly', function () {
  $now = Carbon::now();

  $item = new \App\Models\WishlistItem([
    'product_id' => 101,
    'variant_id' => 505,
    'sort_order' => 1,
    'product_snapshot' => ['price' => 100],
  ]);
  $item->id = 1; // تعيين الـ ID يدوياً ضروري جداً
  $item->created_at = $now;
  $item->updated_at = $now;

  $dto = WishlistItemDTO::fromModel($item);

  expect($dto->id)->toBe(1)
    ->and($dto->product_id)->toBe(101);
});

it('handles null optional fields in fromModel', function () {
  $item = new \App\Models\WishlistItem([
    'product_id' => 202,
    'sort_order' => 0,
  ]);
  $item->id = 2;

  // الحل: بما أن الـ DTO يتوقع string، يجب أن نوفر تاريخاً 
  // أو نغير الـ DTO ليقبل null. لنمرر تاريخاً الآن:
  $item->created_at = now();
  $item->updated_at = now();

  $dto = WishlistItemDTO::fromModel($item);

  expect($dto->variant_id)->toBeNull()
    ->and($dto->created_at)->toBeString(); // التأكد أنه نص وليس null
});

it('converts the item dto to array accurately', function () {
  $snapshot = ['sku' => 'ABC-123'];
  $dto = new WishlistItemDTO(
    id: 1,
    product_id: 10,
    variant_id: 20,
    sort_order: 5,
    product_snapshot: $snapshot,
    created_at: '2026-05-10T14:00:00.000Z',
    updated_at: '2026-05-10T14:00:00.000Z'
  );

  $result = $dto->toArray();

  expect($result)->toBeArray()
    ->and($result['id'])->toBe(1)
    ->and($result['product_snapshot'])->toBe($snapshot)
    ->and($result['created_at'])->toBe('2026-05-10T14:00:00.000Z');
});
