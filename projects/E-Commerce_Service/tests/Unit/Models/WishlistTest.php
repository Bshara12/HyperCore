<?php

namespace Tests\Unit\Models;

use App\Models\Wishlist;
use App\Models\WishlistItem;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('can determine wishlist ownership', function () {
  $wishlist = new Wishlist([
    'user_id' => 10,
    'guest_token' => 'guest_abc_123'
  ]);

  expect($wishlist->isOwnedBy(10))->toBeTrue()
    ->and($wishlist->isOwnedBy(99))->toBeFalse()
    ->and($wishlist->isGuestOwnedBy('guest_abc_123'))->toBeTrue()
    ->and($wishlist->isGuestOwnedBy('wrong_token'))->toBeFalse();
});

it('correctly identifies public and private visibility', function () {
  $publicWishlist = new Wishlist(['visibility' => 'public']);
  $privateWishlist = new Wishlist(['visibility' => 'private']);

  expect($publicWishlist->isPublic())->toBeTrue()
    ->and($privateWishlist->isPublic())->toBeFalse();
});

it('has many items and respects sort order', function () {
  // 1. إنشاء قائمة أمنيات
  $wishlist = Wishlist::create([
    'user_id' => 1,
    'name' => 'My Tech Favorites',
    'visibility' => 'public'
  ]);

  // 2. إنشاء عناصر بترتيب مختلف
  WishlistItem::create([
    'wishlist_id' => $wishlist->id,
    'product_id' => 101,
    'sort_order' => 2
  ]);

  WishlistItem::create([
    'wishlist_id' => $wishlist->id,
    'product_id' => 102,
    'sort_order' => 1 // هذا يجب أن يظهر أولاً
  ]);

  // 3. التحقق من العلاقة والترتيب
  expect($wishlist->items)->toHaveCount(2)
    ->and($wishlist->items->first()->product_id)->toBe(102);
});

it('checks if a product exists in the wishlist', function () {
  $wishlist = Wishlist::create([
    'user_id' => 1,
    'name' => 'Shopping List'
  ]);

  WishlistItem::create([
    'wishlist_id' => $wishlist->id,
    'product_id' => 50,
    'variant_id' => 500
  ]);

  expect($wishlist->hasProduct(50, 500))->toBeTrue()
    ->and($wishlist->hasProduct(50))->toBeFalse() // بدون Variant
    ->and($wishlist->hasProduct(99))->toBeFalse(); // منتج غير موجود
});

it('correctly casts boolean attributes', function () {
  $wishlist = new Wishlist([
    'is_default' => 1,
    'is_shareable' => '0'
  ]);

  expect($wishlist->is_default)->toBeTrue()
    ->and($wishlist->is_shareable)->toBeFalse();
});

it('filters wishlists by user using scope', function () {
  Wishlist::create(['user_id' => 1, 'name' => 'User 1 List']);
  Wishlist::create(['user_id' => 2, 'name' => 'User 2 List']);

  $filtered = Wishlist::forUser(1)->get();

  expect($filtered)->toHaveCount(1)
    ->and($filtered->first()->user_id)->toBe(1);
});

it('filters wishlists by guest token using scope', function () {
  Wishlist::create(['guest_token' => 'guest_789', 'name' => 'Guest List']);
  Wishlist::create(['guest_token' => 'other_token', 'name' => 'Other List']);

  $filtered = Wishlist::forGuest('guest_789')->get();

  expect($filtered)->toHaveCount(1)
    ->and($filtered->first()->guest_token)->toBe('guest_789');
});

it('filters wishlists by visibility using public and private scopes', function () {
  Wishlist::create(['visibility' => 'public', 'name' => 'Public List']);
  Wishlist::create(['visibility' => 'private', 'name' => 'Private List']);
  Wishlist::create(['visibility' => 'private', 'name' => 'Another Private']);

  // اختبار scopePublic
  $publicLists = Wishlist::public()->get();
  expect($publicLists)->toHaveCount(1)
    ->and($publicLists->first()->visibility)->toBe('public');

  // اختبار scopePrivate
  $privateLists = Wishlist::private()->get();
  expect($privateLists)->toHaveCount(2)
    ->and($privateLists->first()->visibility)->toBe('private');
});
