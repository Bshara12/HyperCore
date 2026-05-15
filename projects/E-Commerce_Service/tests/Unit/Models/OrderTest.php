<?php

namespace Tests\Unit\Models;

use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Database\Eloquent\Collection;

uses(RefreshDatabase::class);

it('casts address attribute to array', function () {
  // 1. إنشاء طلب مع مصفوفة عنوان
  $addressData = [
    'city' => 'Riyadh',
    'street' => 'King Fahd Road',
    'zip' => '12345'
  ];

  $order = Order::create([
    'project_id' => 1,
    'user_id' => 1,
    'address' => $addressData,
    'total_price' => 500.00
  ]);

  // 2. التحقق من أن الحقل يتم تحويله تلقائياً لمصفوفة PHP
  expect($order->address)->toBeArray()
    ->and($order->address['city'])->toBe('Riyadh')
    ->and($order->address)->toEqual($addressData);
});

it('has many items relation', function () {
  // 1. إنشاء طلب
  $order = Order::create([
    'project_id' => 1,
    'user_id' => 1,
    'total_price' => 1000
  ]);

  // 2. إنشاء عناصر مرتبطة (بافتراض وجود موديل و Factory لـ OrderItem)
  // ملاحظة: إذا لم يكن لديك Factory لـ OrderItem، استخدم OrderItem::create
  OrderItem::factory()->count(3)->create([
    'order_id' => $order->id
  ]);

  // 3. التحقق من العلاقة
  expect($order->items)->toBeInstanceOf(Collection::class)
    ->and($order->items)->toHaveCount(3)
    ->and($order->items->first())->toBeInstanceOf(OrderItem::class);
});

it('can store and retrieve order details', function () {
  $order = Order::create([
    'project_id' => 99,
    'user_id' => 10,
    'status' => 'pending',
    'total_price' => 250.75
  ]);

  expect($order->status)->toBe('pending')
    ->and($order->total_price)->toEqual(250.75)
    ->and($order->project_id)->toBe(99);
});
