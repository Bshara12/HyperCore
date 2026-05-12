<?php

use App\Domains\E_Commerce\DTOs\Wishlist\WishlistDetailsDTO;
use Carbon\Carbon;

it('transforms a wishlist model into details dto correctly', function () {
  $now = Carbon::now();

  // إنشاء WishlistItem حقيقي بدلاً من Anonymous Class
  $item = new \App\Models\WishlistItem([
    'id' => 1,
    'product_id' => 10,
    'sort_order' => 0,
  ]);
  $item->id = 1; // تعيين المعرف يدوياً لأن الـ mass assignment قد يمنعه
  $item->created_at = $now;
  $item->updated_at = $now;

  $wishlist = new \App\Models\Wishlist([
    'id' => 1,
    'name' => 'Summer Collection',
    'is_default' => 1,
    'visibility' => 'public',
    'is_shareable' => true,
  ]);
  $wishlist->id = 1;
  $wishlist->created_at = $now;
  $wishlist->updated_at = $now;
  $wishlist->setRelation('items', collect([$item]));

  $dto = WishlistDetailsDTO::fromModel($wishlist);

  expect($dto->name)->toBe('Summer Collection')
    ->and($dto->items)->toHaveCount(1)
    ->and($dto->items[0]['id'])->toBe(1);
});

it('converts the details dto to array correctly', function () {
  // اختبار دالة toArray
  $dto = new WishlistDetailsDTO(
    id: 1,
    name: 'Test',
    is_default: false,
    visibility: 'private',
    is_shareable: false,
    share_token: null,
    user_id: 1,
    guest_token: null,
    items: [['id' => 10]],
    created_at: '2026-05-10T12:00:00.000Z',
    updated_at: '2026-05-10T12:00:00.000Z'
  );

  $result = $dto->toArray();

  expect($result)->toBeArray()
    ->and($result['name'])->toBe('Test')
    ->and($result['items'])->toEqual([['id' => 10]])
    ->and($result['created_at'])->toBe('2026-05-10T12:00:00.000Z');
});
