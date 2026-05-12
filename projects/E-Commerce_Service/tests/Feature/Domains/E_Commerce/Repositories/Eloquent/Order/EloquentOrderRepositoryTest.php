<?php

use App\Domains\E_Commerce\Repositories\Eloquent\Order\EloquentOrderRepository;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/** @var EloquentOrderRepository|null $repository */
$repository = null;

beforeEach(function () use (&$repository) {
  $repository = new EloquentOrderRepository();
});

/**
 * 1. Test: create & findById
 */
it('can create an order and find it by id', function () use (&$repository) {
  $data = [
    'project_id' => 1,
    'user_id' => 10,
    'status' => 'pending',
    'total_price' => 500,
    'address' => 'Test Address'
  ];

  $order = $repository->create($data);

  expect($order->id)->not->toBeNull();

  $found = $repository->findById($order->id);
  expect($found->id)->toBe($order->id);

  // اختبار حالة عدم وجود الطلب لتغطية الـ return ?Order
  expect($repository->findById(9999))->toBeNull();
});

/**
 * 2. Test: findByIdForUser
 */
it('can find an order for a specific user and project', function () use (&$repository) {
  $order = Order::factory()->create([
    'project_id' => 2,
    'user_id' => 20
  ]);

  $found = $repository->findByIdForUser($order->id, 2, 20);
  expect($found)->not->toBeNull()
    ->and($found->id)->toBe($order->id);

  // حالة بيانات خاطئة للتأكد من الـ null
  expect($repository->findByIdForUser($order->id, 1, 1))->toBeNull();
});

/**
 * 3. Test: loadItems
 */
it('can load order items relationship', function () use (&$repository) {
  $order = Order::factory()->create();
  OrderItem::factory()->count(2)->create(['order_id' => $order->id]);

  $loadedOrder = $repository->loadItems($order);

  expect($loadedOrder->relationLoaded('items'))->toBeTrue()
    ->and($loadedOrder->items)->toHaveCount(2);
});

/**
 * 4. Test: getUserOrders
 */
it('retrieves all orders for a specific user with items', function () use (&$repository) {
  $userId = 30;
  $projectId = 3;
  Order::factory()->count(2)->create(['user_id' => $userId, 'project_id' => $projectId]);

  $orders = $repository->getUserOrders($projectId, $userId);

  expect($orders)->toHaveCount(2);
  expect($orders->first()->relationLoaded('items'))->toBeTrue();
});

/**
 * 5. Test: getAllOrders (Filters & Pagination)
 */
it('filters all orders by status and user_id with pagination', function () use (&$repository) {
  $projectId = 4;

  // طلب يحقق الفلاتر
  Order::factory()->create(['project_id' => $projectId, 'user_id' => 1, 'status' => 'completed']);
  // طلب لا يحقق الفلتر (حالة مختلفة)
  Order::factory()->create(['project_id' => $projectId, 'user_id' => 1, 'status' => 'pending']);
  // طلب لا يحقق الفلتر (مستخدم مختلف)
  Order::factory()->create(['project_id' => $projectId, 'user_id' => 2, 'status' => 'completed']);

  // 1. تجربة فلتر الـ status فقط
  $filteredByStatus = $repository->getAllOrders($projectId, ['status' => 'completed']);
  expect($filteredByStatus->total())->toBe(2);

  // 2. تجربة فلتر الـ user_id فقط
  $filteredByUser = $repository->getAllOrders($projectId, ['user_id' => 1]);
  expect($filteredByUser->total())->toBe(2);

  // 3. تجربة الفلترين معاً (تغطية المسارين)
  $fullyFiltered = $repository->getAllOrders($projectId, [
    'status' => 'completed',
    'user_id' => 1
  ]);
  expect($fullyFiltered->total())->toBe(1);

  // 4. تجربة الترقيم (Pagination)
  expect($fullyFiltered)->toBeInstanceOf(\Illuminate\Pagination\LengthAwarePaginator::class);
});

/**
 * 6. Test: findDetailedForUser
 */
it('finds a detailed order with specific columns for a user', function () use (&$repository) {
  $order = Order::factory()->create([
    'id' => 500,
    'project_id' => 5,
    'user_id' => 50,
    'status' => 'shipped'
  ]);
  OrderItem::factory()->create(['order_id' => $order->id]);

  $detailed = $repository->findDetailedForUser(500, 5, 50);

  expect($detailed)->not->toBeNull();
  expect($detailed->relationLoaded('items'))->toBeTrue();
  // التأكد من جلب أحد الأعمدة المحددة في الـ select
  expect($detailed->status)->toBe('shipped');
});

/**
 * 7. Test: updateStatus & updateItemsStatus
 */
it('can update order status and its items status', function () use (&$repository) {
  // 1. إنشاء طلب
  $order = Order::factory()->create(['status' => 'pending']);

  // 2. إنشاء عناصر للطلب باستخدام الـ Factory (لتجنب أخطاء أسماء الحقول)
  OrderItem::factory()->count(3)->create([
    'order_id' => $order->id,
    'status' => 'pending'
  ]);

  // 3. تحديث حالة الطلب عبر المستودع
  $repository->updateStatus($order->id, 'completed');

  // التأكد من تحديث جدول الطلبات
  $this->assertDatabaseHas('orders', [
    'id' => $order->id,
    'status' => 'completed'
  ]);

  // 4. تحديث حالة عناصر الطلب عبر المستودع (التي تستخدم DB::table داخلياً)
  $repository->updateItemsStatus($order->id, 'delivered');

  // التأكد من تحديث جدول عناصر الطلب
  $this->assertDatabaseHas('order_items', [
    'order_id' => $order->id,
    'status' => 'delivered'
  ]);

  // التأكد من أن جميع العناصر (الـ 3) قد تحددت حالتهم
  $updatedItemsCount = DB::table('order_items')
    ->where('order_id', $order->id)
    ->where('status', 'delivered')
    ->count();

  expect($updatedItemsCount)->toBe(3);
});
