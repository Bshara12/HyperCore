<?php

use App\Domains\E_Commerce\Repositories\Eloquent\Wishlist\WishlistRepository;
use App\Models\Wishlist;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/** @var WishlistRepository|null $repository */
$repository = null;

beforeEach(function () use (&$repository) {
  $repository = new WishlistRepository();
});

/**
 * 1. Test: CRUD Basics
 */
it('can perform basic CRUD on wishlist', function () use (&$repository) {
  $data = ['name' => 'My Tech List', 'user_id' => 1, 'is_default' => true];

  $wishlist = $repository->create($data);
  expect($wishlist->id)->not->toBeNull();

  $repository->update($wishlist, ['name' => 'Updated List']);
  $this->assertDatabaseHas('wishlists', ['id' => $wishlist->id, 'name' => 'Updated List']);

  $found = $repository->findById($wishlist->id);
  expect($found->id)->toBe($wishlist->id);

  $repository->delete($wishlist);
  $this->assertDatabaseMissing('wishlists', ['id' => $wishlist->id]);
});

/**
 * 2. Test: User Specific Methods
 */
it('handles user specific wishlist operations', function () use (&$repository) {
  $userId = 99;

  // إنشاء قائمة افتراضية وقائمة عادية
  Wishlist::factory()->create(['user_id' => $userId, 'name' => 'B List', 'is_default' => false]);
  Wishlist::factory()->create(['user_id' => $userId, 'name' => 'A List', 'is_default' => true]);

  // getByUserId (يجب أن تكون الافتراضية أولاً ثم الترتيب الأبجدي)
  $lists = $repository->getByUserId($userId);
  expect($lists)->toHaveCount(2);
  expect($lists->first()->is_default)->toBeTrue();
  expect($lists->last()->name)->toBe('B List');

  // getDefaultByUserId
  $default = $repository->getDefaultByUserId($userId);
  expect($default->is_default)->toBeTrue();

  // existsByName
  expect($repository->existsByName($userId, 'A List'))->toBeTrue();
  expect($repository->existsByName($userId, 'Non Existent'))->toBeFalse();

  // findByIdForUser
  $list = $lists->first();
  expect($repository->findByIdForUser($list->id, $userId))->not->toBeNull();
  expect($repository->findByIdForUser($list->id, 888))->toBeNull();
});

/**
 * 3. Test: Guest Specific Methods
 */
it('handles guest specific wishlist operations', function () use (&$repository) {
  $token = 'guest-123';

  Wishlist::factory()->create(['guest_token' => $token, 'is_default' => true]);

  expect($repository->getByGuestToken($token))->toHaveCount(1);
  expect($repository->getDefaultByGuestToken($token))->not->toBeNull();

  $list = $repository->getDefaultByGuestToken($token);
  expect($repository->findByIdForGuest($list->id, $token))->not->toBeNull();
  expect($repository->findByIdForGuest($list->id, 'wrong-token'))->toBeNull();
});

/**
 * 4. Test: Sharing & Public Access
 */
it('finds wishlists by share token', function () use (&$repository) {
  $shareToken = 'secret-share';

  // قائمة قابلة للمشاركة وعامة
  $wishlist = Wishlist::factory()->create([
    'is_shareable' => true,
    'share_token' => $shareToken,
    'visibility' => 'public' // نفترض وجود public scope
  ]);

  expect($repository->findByShareToken($shareToken))->not->toBeNull();
  expect($repository->findByShareTokenWithItems($shareToken))->not->toBeNull();

  // فحص التوكن الخاطئ أو غير القابلة للمشاركة
  expect($repository->findByShareToken('wrong-token'))->toBeNull();
});

/**
 * 5. Test: Eager Loading (With Items)
 */
it('loads wishlist with items relationship', function () use (&$repository) {
  $wishlist = Wishlist::factory()->hasItems(3)->create(['user_id' => 1]);

  $foundById = $repository->findByIdWithItems($wishlist->id);
  expect($foundById->relationLoaded('items'))->toBeTrue();
  expect($foundById->items)->toHaveCount(3);

  $foundForUser = $repository->findByIdWithItemsForUser($wishlist->id, 1);
  expect($foundForUser)->not->toBeNull();
  expect($foundForUser->relationLoaded('items'))->toBeTrue();
});
