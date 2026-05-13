<?php

namespace Tests\Unit\Models;

use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('belongs to an order', function () {
  // 1. إنشاء الطلب الأب
  $order = Order::create([
    'project_id' => 1,
    'user_id' => 1,
    'total_price' => 500
  ]);

  // 2. إنشاء عنصر الطلب مع إضافة حقل total المطلوب
  $orderItem = OrderItem::create([
    'order_id' => $order->id,
    'product_id' => 101,
    'quantity' => 2,
    'price' => 250,
    'total' => 500 // أضفنا هذا الحقل بناءً على خطأ IntegrityConstraint
  ]);

  // 3. التحقق من العلاقة
  expect($orderItem->order)->toBeInstanceOf(Order::class)
    ->and($orderItem->order->id)->toBe($order->id);
});

it('stores item attributes correctly', function () {
  $orderItem = new OrderItem([
    'order_id' => 1,
    'product_id' => 99,
    'quantity' => 5,
    'price' => 10.5,
    'total' => 52.5
  ]);

  expect($orderItem->product_id)->toBe(99)
    ->and($orderItem->quantity)->toBe(5)
    ->and($orderItem->total)->toEqual(52.5);
});
