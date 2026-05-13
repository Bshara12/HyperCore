<?php

use App\Domains\E_Commerce\Repositories\Eloquent\Wishlist\WishlistItemRepository;
use App\Models\WishlistItem;
use App\Models\Wishlist; // تأكد من استيراد موديل الـ Wishlist
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/** @var WishlistItemRepository|null $repository */
$repository = null;

beforeEach(function () use (&$repository) {
  $repository = new WishlistItemRepository();
});

/**
 * 1. Test: create, update, delete & findById
 */
it('can perform basic CRUD operations on wishlist items', function () use (&$repository) {
  // إنشاء Wishlist حقيقية لتجنب خطأ الـ Foreign Key
  $wishlist = Wishlist::factory()->create();

  $data = [
    'wishlist_id' => $wishlist->id,
    'product_id'  => 101,
    'variant_id'  => null,
    'sort_order'  => 1,
    // إضافة الحقول التي ظهرت في الخطأ كحقول إجبارية
    'product_snapshot' => json_encode(['name' => 'Test Product']),
    'price_when_added' => 100.0
  ];

  $item = $repository->create($data);
  expect($item->id)->not->toBeNull();

  $found = $repository->findById($item->id);
  expect($found->id)->toBe($item->id);

  $repository->update($item, ['sort_order' => 5]);
  $this->assertDatabaseHas('wishlist_items', ['id' => $item->id, 'sort_order' => 5]);

  $result = $repository->delete($item);
  expect($result)->toBeTrue();
  $this->assertDatabaseMissing('wishlist_items', ['id' => $item->id]);
});

/**
 * 2. Test: findByIdInWishlist
 */
it('can find an item specifically within a wishlist', function () use (&$repository) {
  $item = WishlistItem::factory()->create();

  $found = $repository->findByIdInWishlist($item->id, $item->wishlist_id);
  expect($found)->not->toBeNull()
    ->and($found->id)->toBe($item->id);

  expect($repository->findByIdInWishlist($item->id, 9999))->toBeNull();
});

/**
 * 3. Test: getByWishlistId
 */
it('retrieves all items for a wishlist using the ordered scope', function () use (&$repository) {
  $wishlist = Wishlist::factory()->create();
  WishlistItem::factory()->create(['wishlist_id' => $wishlist->id, 'sort_order' => 2]);
  WishlistItem::factory()->create(['wishlist_id' => $wishlist->id, 'sort_order' => 1]);

  $items = $repository->getByWishlistId($wishlist->id);

  expect($items)->toHaveCount(2);
  // التحقق من أن الـ scope "ordered" يعمل (الأقل أولاً)
  expect($items->first()->sort_order)->toBe(1);
});

/**
 * 4. Test: existsInWishlist
 */
it('checks if a product or variant exists in a wishlist', function () use (&$repository) {
  $item = WishlistItem::factory()->create([
    'product_id' => 100,
    'variant_id' => 500
  ]);

  expect($repository->existsInWishlist($item->wishlist_id, 100, 500))->toBeTrue();
  expect($repository->existsInWishlist($item->wishlist_id, 100, null))->toBeFalse();
  expect($repository->existsInWishlist($item->wishlist_id, 999, null))->toBeFalse();
});

/**
 * 5. Test: getHighestSortOrder & countByWishlistId
 */
it('calculates the maximum sort order and counts items correctly', function () use (&$repository) {
  $wishlist = Wishlist::factory()->create();

  // حالة القائمة الفارغة
  expect($repository->getHighestSortOrder($wishlist->id))->toBe(0);

  WishlistItem::factory()->create(['wishlist_id' => $wishlist->id, 'sort_order' => 10]);
  WishlistItem::factory()->create(['wishlist_id' => $wishlist->id, 'sort_order' => 25]);

  expect($repository->getHighestSortOrder($wishlist->id))->toBe(25);
  expect($repository->countByWishlistId($wishlist->id))->toBe(2);
});
