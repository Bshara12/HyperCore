<?php

use App\Domains\E_Commerce\Repositories\Eloquent\Cart\EloquentCartRepository;
use App\Models\Cart;
use App\Models\CartItem;
use Illuminate\Foundation\Testing\RefreshDatabase;

// لاحظ أننا حذفنا أسطر use function Pest\Laravel... التي كانت تسبب المشاكل
uses(RefreshDatabase::class);

/** @var EloquentCartRepository|null $repository */
$repository = null;

beforeEach(function () use (&$repository) {
  $repository = new EloquentCartRepository();
});

/**
 * Test: getOrCreate
 */
it('creates a new cart if it does not exist', function () use (&$repository) {
  $projectId = 1;
  $userId = 10;

  $cart = $repository->getOrCreate($projectId, $userId);

  expect($cart)->toBeInstanceOf(Cart::class)
    ->and($cart->project_id)->toBe($projectId)
    ->and($cart->user_id)->toBe($userId);

  // استخدام دالة لارافيل الأصلية عبر $this
  $this->assertDatabaseHas('carts', [
    'project_id' => $projectId,
    'user_id' => $userId
  ]);
});

it('returns the existing cart if it already exists', function () use (&$repository) {
  $existingCart = Cart::factory()->create([
    'project_id' => 1,
    'user_id' => 10
  ]);

  $cart = $repository->getOrCreate(1, 10);

  expect($cart->id)->toBe($existingCart->id);
  expect(Cart::count())->toBe(1);
});

/**
 * Test: findByProjectAndUser
 */
it('can find a cart by project and user ids', function () use (&$repository) {
  $cart = Cart::factory()->create([
    'project_id' => 5,
    'user_id' => 20
  ]);

  $found = $repository->findByProjectAndUser(5, 20);

  expect($found)->not->toBeNull()
    ->and($found->id)->toBe($cart->id);
});

/**
 * Test: loadItems
 */
it('can load cart items relationship', function () use (&$repository) {
  $cart = Cart::factory()->create();
  CartItem::factory()->count(3)->create(['cart_id' => $cart->id]);

  $loadedCart = $repository->loadItems($cart);

  expect($loadedCart->relationLoaded('items'))->toBeTrue()
    ->and($loadedCart->items)->toHaveCount(3);
});

/**
 * Test: findById
 */
it('can find a cart by its primary id', function () use (&$repository) {
  $cart = Cart::factory()->create();

  $found = $repository->findById($cart->id);

  expect($found)->not->toBeNull()
    ->and($found->id)->toBe($cart->id);
});

/**
 * Test: delete
 */
it('can delete a cart by its id', function () use (&$repository) {
  $cart = Cart::factory()->create();

  $repository->delete($cart->id);

  // استخدام دالة لارافيل الأصلية عبر $this
  $this->assertDatabaseMissing('carts', ['id' => $cart->id]);
});
