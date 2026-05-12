<?php

use App\Domains\E_Commerce\Repositories\Eloquent\Order\EloquentOrderItemRepository;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/** @var EloquentOrderItemRepository|null $repository */
$repository = null;

beforeEach(function () use (&$repository) {
  $repository = new EloquentOrderItemRepository();
});

/**
 * 1. Test: create & findById
 */
it('can create an order item and find it by id', function () use (&$repository) {
  // نحتاج إنشاء طلب أولاً لتجنب فشل الـ Foreign Key
  $order = Order::factory()->create();

  $data = [
    'order_id'   => $order->id,
    'product_id' => 101,
    'price'      => 150,
    'quantity'   => 2,
    'total'      => 300, // إضافة الحقل المطلوب لإصلاح خطأ NOT NULL
    'status'     => 'pending'
  ];

  $item = $repository->create($data);

  expect($item->id)->not->toBeNull();

  $found = $repository->findById($item->id);
  expect($found->id)->toBe($item->id);

  // تغطية حالة عدم الوجود
  expect($repository->findById(9999))->toBeNull();
});

/**
 * 2. Test: update
 */
it('can update an order item instance', function () use (&$repository) {
  $item = OrderItem::factory()->create(['status' => 'pending']);

  $updated = $repository->update($item, ['status' => 'processing']);

  expect($updated->status)->toBe('processing');
  $this->assertDatabaseHas('order_items', [
    'id' => $item->id,
    'status' => 'processing'
  ]);
});

/**
 * 3. Test: findByOrderAndItem
 */
it('can find an item by its order_id and product_id', function () use (&$repository) {
  // استخدام Factory لضمان وجود الـ Order والـ Product بشكل صحيح
  $item = OrderItem::factory()->create([
    'product_id' => 500
  ]);

  $found = $repository->findByOrderAndItem($item->order_id, 500);

  expect($found)->not->toBeNull()
    ->and($found->id)->toBe($item->id);

  expect($repository->findByOrderAndItem($item->order_id, 999))->toBeNull();
});

/**
 * 4. Test: updateStatus
 */
it('can update the status of an item by its id', function () use (&$repository) {
  $item = OrderItem::factory()->create(['status' => 'pending']);

  $repository->updateStatus($item->id, 'shipped');

  $this->assertDatabaseHas('order_items', [
    'id' => $item->id,
    'status' => 'shipped'
  ]);
});

/**
 * 5. Test: findByOrderId
 */
it('retrieves all items belonging to a specific order', function () use (&$repository) {
  $order = Order::factory()->create();
  // إنشاء عناصر مرتبطة بهذا الطلب
  OrderItem::factory()->count(3)->create(['order_id' => $order->id]);

  // إنشاء عنصر لطلب آخر للتأكد من دقة الفلترة
  OrderItem::factory()->create();

  $items = $repository->findByOrderId($order->id);

  expect($items)->toHaveCount(3);
  foreach ($items as $item) {
    expect($item->order_id)->toBe($order->id);
  }
});
