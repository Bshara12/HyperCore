<?php

namespace Tests\Unit\Models;

use App\Models\Cart;
use App\Models\CartItem;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('belongs to a cart', function () {
  // 1. إنشاء سلة
  $cart = Cart::create([
    'project_id' => 1,
    'user_id' => 1
  ]);

  // 2. إنشاء عنصر وربطه بالسلة
  $item = CartItem::factory()->create([
    'cart_id' => $cart->id,
    'item_id' => 10,
    'quantity' => 5
  ]);

  // 3. التحقق من العلاقة العكسية
  expect($item->cart)->toBeInstanceOf(Cart::class)
    ->and($item->cart->id)->toBe($cart->id)
    ->and($item->cart->project_id)->toBe(1);
});

it('stores the attributes correctly', function () {
  $cart = Cart::create(['project_id' => 1, 'user_id' => 1]);

  $item = CartItem::create([
    'cart_id' => $cart->id,
    'item_id' => 99,
    'quantity' => 10
  ]);

  expect($item->item_id)->toBe(99)
    ->and($item->quantity)->toBe(10);
});
