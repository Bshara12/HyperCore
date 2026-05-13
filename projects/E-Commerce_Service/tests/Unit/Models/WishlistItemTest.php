<?php

namespace Tests\Unit\Models;

use App\Models\Wishlist;
use App\Models\WishlistItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Database\Eloquent\Collection;

uses(RefreshDatabase::class);

it('belongs to a wishlist', function () {
  // 1. إنشاء القائمة الأم
  $wishlist = Wishlist::create([
    'user_id' => 1,
    'name' => 'Main Wishlist'
  ]);

  // 2. إنشاء عنصر القائمة
  $item = WishlistItem::create([
    'wishlist_id' => $wishlist->id,
    'product_id' => 101,
    'price_when_added' => 99.99
  ]);

  // 3. التحقق من العلاقة العكسية
  expect($item->wishlist)->toBeInstanceOf(Wishlist::class)
    ->and($item->wishlist->id)->toBe($wishlist->id);
});

it('correctly identifies if it is a variant', function () {
  $itemWithVariant = new WishlistItem(['variant_id' => 500]);
  $itemWithoutVariant = new WishlistItem(['variant_id' => null]);

  expect($itemWithVariant->isVariant())->toBeTrue()
    ->and($itemWithoutVariant->isVariant())->toBeFalse();
});

it('correctly casts complex attributes', function () {
  $snapshot = ['name' => 'iPhone 15', 'color' => 'Blue'];

  $item = new WishlistItem([
    'added_from_cart' => 1,
    'notify_on_price_drop' => '0',
    'product_snapshot' => $snapshot,
    'price_when_added' => '1250.50'
  ]);

  expect($item->added_from_cart)->toBeTrue()
    ->and($item->notify_on_price_drop)->toBeFalse()
    ->and($item->product_snapshot)->toBeArray()
    ->and($item->product_snapshot['name'])->toBe('iPhone 15')
    ->and($item->price_when_added)->toBe('1250.50'); // كـ string بسبب الـ decimal cast في Eloquent
});

it('filters items by product using scope', function () {
  $wishlist = Wishlist::create(['user_id' => 1, 'name' => 'Test']);

  WishlistItem::create(['wishlist_id' => $wishlist->id, 'product_id' => 1]);
  WishlistItem::create(['wishlist_id' => $wishlist->id, 'product_id' => 2]);

  $filtered = WishlistItem::forProduct(1)->get();

  expect($filtered)->toHaveCount(1)
    ->and($filtered->first()->product_id)->toBe(1);
});

it('orders items by sort_order using scope', function () {
  $wishlist = Wishlist::create(['user_id' => 1, 'name' => 'Sort Test']);

  // إنشاء عناصر بترتيب عشوائي
  WishlistItem::create(['wishlist_id' => $wishlist->id, 'product_id' => 1, 'sort_order' => 10]);
  WishlistItem::create(['wishlist_id' => $wishlist->id, 'product_id' => 2, 'sort_order' => 1]);
  WishlistItem::create(['wishlist_id' => $wishlist->id, 'product_id' => 3, 'sort_order' => 5]);

  // استدعاء الـ scope
  $orderedItems = WishlistItem::ordered()->get();

  expect($orderedItems->first()->sort_order)->toBe(1)
    ->and($orderedItems->last()->sort_order)->toBe(10)
    ->and($orderedItems->get(1)->sort_order)->toBe(5);
});

it('filters items by variant using scope', function () {
  $wishlist = Wishlist::create(['user_id' => 1, 'name' => 'Variant Test']);

  // إنشاء عنصر مع variant وعنصر بدون
  WishlistItem::create(['wishlist_id' => $wishlist->id, 'product_id' => 1, 'variant_id' => 500]);
  WishlistItem::create(['wishlist_id' => $wishlist->id, 'product_id' => 1, 'variant_id' => null]);

  // 1. اختبار البحث عن variant محدد
  $withVariant = WishlistItem::forVariant(500)->get();
  expect($withVariant)->toHaveCount(1)
    ->and($withVariant->first()->variant_id)->toBe(500);

  // 2. اختبار البحث عن العناصر التي ليس لها variant (null)
  $withoutVariant = WishlistItem::forVariant(null)->get();
  expect($withoutVariant)->toHaveCount(1)
    ->and($withoutVariant->first()->variant_id)->toBeNull();
});
