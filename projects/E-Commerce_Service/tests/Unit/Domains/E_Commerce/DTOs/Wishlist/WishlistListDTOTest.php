<?php

use App\Domains\E_Commerce\DTOs\Wishlist\WishlistListDTO;
use App\Models\Wishlist;
use Carbon\Carbon;

it('transforms wishlist model to list dto using items_count attribute', function () {
  $now = Carbon::now();
  $wishlist = new \App\Models\Wishlist([
    'name' => 'Summer List',
    'visibility' => 'public',
    'is_default' => true,
  ]);
  $wishlist->id = 1;
  $wishlist->items_count = 5;
  $wishlist->created_at = $now;

  $dto = WishlistListDTO::fromModel($wishlist);

  expect($dto->items_count)->toBe(5)
    ->and($dto->id)->toBe(1)
    ->and($dto->name)->toBe('Summer List');
});

it('calculates items_count from relationship if attribute is missing', function () {
  $wishlist = new \App\Models\Wishlist([
    'name' => 'Winter List',
    'visibility' => 'private',
    'is_default' => false,
  ]);
  $wishlist->id = 2;
  $wishlist->setRelation('items', collect([
    new \App\Models\WishlistItem(),
    new \App\Models\WishlistItem()
  ]));
  $wishlist->created_at = Carbon::now();

  $dto = WishlistListDTO::fromModel($wishlist);

  expect($dto->items_count)->toBe(2)
    ->and($dto->visibility)->toBe('private');
});

it('converts list dto to array correctly', function () {
  $dto = new WishlistListDTO(
    id: 10,
    name: 'Tech Gadgets',
    is_default: true,
    visibility: 'public',
    is_shareable: true,
    share_token: 'tk-99',
    items_count: 3,
    created_at: '2026-05-10T14:00:00.000Z'
  );

  $result = $dto->toArray();

  expect($result)->toBeArray()
    ->and($result['items_count'])->toBe(3)
    ->and($result['share_token'])->toBe('tk-99');
});
