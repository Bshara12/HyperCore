<?php

namespace Tests\Unit\Models;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\return_requests;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('belongs to an order item', function () {
  // 1. إنشاء الطلب الأب
  $order = Order::create([
    'project_id' => 1,
    'user_id' => 1,
    'total_price' => 500
  ]);

  // 2. إنشاء عنصر الطلب
  $orderItem = OrderItem::create([
    'order_id' => $order->id,
    'product_id' => 101,
    'quantity' => 2,
    'price' => 250,
    'total' => 500
  ]);

  // 3. إنشاء طلب الإرجاع مع تلبية جميع قيود الـ NOT NULL
  $returnRequest = return_requests::create([
    'user_id'       => 1,
    'order_id'      => $order->id,
    'order_item_id' => $orderItem->id,
    'project_id'    => 1,
    'quantity'      => 1,
    'description'   => 'Defective item',
    'status'        => 'pending'
  ]);

  // 4. التحقق من العلاقة
  expect($returnRequest->orderItem)->toBeInstanceOf(OrderItem::class)
    ->and($returnRequest->orderItem->id)->toBe($orderItem->id);
});

it('stores return request attributes correctly', function () {
  $returnRequest = new return_requests([
    'user_id' => 1,
    'status'  => 'approved',
    'quantity' => 1
  ]);

  expect($returnRequest->status)->toBe('approved')
    ->and($returnRequest->user_id)->toBe(1)
    ->and($returnRequest->quantity)->toBe(1);
});
