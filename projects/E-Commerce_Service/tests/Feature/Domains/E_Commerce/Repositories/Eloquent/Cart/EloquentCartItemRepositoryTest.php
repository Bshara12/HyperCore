<?php

use App\Domains\E_Commerce\Repositories\Eloquent\Cart\EloquentCartItemRepository;
use App\Models\CartItem;
use App\Models\Cart;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/** @var EloquentCartItemRepository|null $repository */
$repository = null;

beforeEach(function () use (&$repository) {
  $repository = new EloquentCartItemRepository();
});

/**
 * Test: findByCartAndItem
 */
it('can find a specific cart item by cart and item ids', function () use (&$repository) {
  $item = CartItem::factory()->create([
    'item_id' => 500
  ]);

  $found = $repository->findByCartAndItem($item->cart_id, 500);

  expect($found)->not->toBeNull()
    ->and($found->id)->toBe($item->id)
    ->and($found->item_id)->toBe(500);
});

it('returns null when finding a non-existent cart item', function () use (&$repository) {
  $found = $repository->findByCartAndItem(999, 999);
  expect($found)->toBeNull();
});

/**
 * Test: create
 */
it('can create a new cart item record', function () use (&$repository) {
  $cart = Cart::factory()->create();
  $data = [
    'cart_id' => $cart->id,
    'item_id' => 123,
    'quantity' => 5
  ];

  $created = $repository->create($data);

  expect($created)->toBeInstanceOf(CartItem::class)
    ->and($created->item_id)->toBe(123);

  $this->assertDatabaseHas('cart_items', $data);
});

/**
 * Test: update
 */
it('can update an existing cart item instance', function () use (&$repository) {
  $item = CartItem::factory()->create(['quantity' => 1]);
  $newData = ['quantity' => 10];

  $updated = $repository->update($item, $newData);

  expect($updated->quantity)->toBe(10);
  $this->assertDatabaseHas('cart_items', [
    'id' => $item->id,
    'quantity' => 10
  ]);
});

/**
 * Test: delete
 */
it('can delete a cart item instance', function () use (&$repository) {
  $item = CartItem::factory()->create();

  $repository->delete($item);

  $this->assertDatabaseMissing('cart_items', ['id' => $item->id]);
});

/**
 * Test: deleteByIds
 */
it('can delete multiple items by their ids within a specific cart', function () use (&$repository) {
  $cart = Cart::factory()->create();

  // إنشاء 3 عناصر لنفس السلة
  CartItem::factory()->create(['cart_id' => $cart->id, 'item_id' => 1]);
  CartItem::factory()->create(['cart_id' => $cart->id, 'item_id' => 2]);
  CartItem::factory()->create(['cart_id' => $cart->id, 'item_id' => 3]);

  // حذف عنصرين فقط
  $repository->deleteByIds($cart->id, [1, 2]);

  $this->assertDatabaseMissing('cart_items', ['cart_id' => $cart->id, 'item_id' => 1]);
  $this->assertDatabaseMissing('cart_items', ['cart_id' => $cart->id, 'item_id' => 2]);
  $this->assertDatabaseHas('cart_items', ['cart_id' => $cart->id, 'item_id' => 3]);
});

/**
 * Test: deleteByCartId
 */
it('can delete all items belonging to a specific cart', function () use (&$repository) {
  $cart = Cart::factory()->create();
  CartItem::factory()->count(3)->create(['cart_id' => $cart->id]);

  $repository->deleteByCartId($cart->id);

  expect(CartItem::where('cart_id', $cart->id)->count())->toBe(0);
});
