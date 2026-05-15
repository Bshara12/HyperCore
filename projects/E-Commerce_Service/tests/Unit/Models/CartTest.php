<?php

namespace Tests\Unit\Models;

use App\Models\Cart;
use App\Models\CartItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Database\Eloquent\Collection;

uses(RefreshDatabase::class);

it('verifies that a cart has many items relation', function () {
  // إنشاء سلة بالبيانات الإجبارية
  $cart = Cart::create([
    'project_id' => 1,
    'user_id' => 1
  ]);

  // إنشاء عناصر مرتبطة
  CartItem::factory()->count(3)->create([
    'cart_id' => $cart->id,
    'item_id' => rand(1, 100),
    'quantity' => 1
  ]);

  // التأكد من أن العلاقة تعيد مجموعة من نوع CartItem
  expect($cart->items)->toBeInstanceOf(Collection::class)
    ->and($cart->items)->toHaveCount(3)
    ->and($cart->items->first())->toBeInstanceOf(CartItem::class);
});

it('can filter items through the relation', function () {
  $cart = Cart::create(['project_id' => 1, 'user_id' => 1]);

  CartItem::factory()->create([
    'cart_id' => $cart->id,
    'item_id' => 500
  ]);

  // التأكد من إمكانية الوصول لعنصر محدد عبر العلاقة
  expect($cart->items()->where('item_id', 500)->exists())->toBeTrue();
});
