<?php

use App\Domains\E_Commerce\Repositories\Eloquent\ReturnRequest\EloquentReturnRequestRepository;
use App\Models\return_requests;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/** @var EloquentReturnRequestRepository|null $repository */
$repository = null;

beforeEach(function () use (&$repository) {
  $repository = new EloquentReturnRequestRepository();
});

/**
 * 1. Test: create & findById
 */
it('can create a return request and find it by id', function () use (&$repository) {
  $data = [
    'project_id'    => 1,
    'order_id'      => 1, // تم إضافة الحقل المطلوب لإصلاح خطأ NOT NULL
    'order_item_id' => 1,
    'user_id'       => 1,
    'status'        => 'pending'
  ];

  $request = $repository->create($data);

  expect($request->id)->not->toBeNull();

  $found = $repository->findById($request->id);
  expect($found->id)->toBe($request->id);

  expect($repository->findById(9999))->toBeNull();
});

/**
 * 2. Test: findPendingByItem
 */
it('can find a pending return request by order_item_id', function () use (&$repository) {
  $itemId = 55;

  $pending = return_requests::create([
    'project_id'    => 1,
    'order_id'      => 1,
    'order_item_id' => $itemId,
    'user_id'       => 1,
    'status'        => 'pending'
  ]);

  // طلب آخر بحالة مختلفة لنفس العنصر (لتغطية دقة الاستعلام)
  return_requests::create([
    'project_id'    => 1,
    'order_id'      => 1,
    'order_item_id' => $itemId,
    'user_id'       => 1,
    'status'        => 'rejected'
  ]);

  $found = $repository->findPendingByItem($itemId);

  expect($found)->not->toBeNull()
    ->and($found->id)->toBe($pending->id)
    ->and($found->status)->toBe('pending');
});

/**
 * 3. Test: update
 */
it('can update a return request instance', function () use (&$repository) {
  $request = return_requests::create([
    'project_id'    => 1,
    'order_id'      => 1,
    'order_item_id' => 1,
    'user_id'       => 1,
    'status'        => 'pending'
  ]);

  $updated = $repository->update($request, ['status' => 'approved']);

  expect($updated->status)->toBe('approved');
  $this->assertDatabaseHas('return_requests', [
    'id'     => $request->id,
    'status' => 'approved'
  ]);
});

/**
 * 4. Test: getByProject
 */
it('retrieves paginated return requests for a project', function () use (&$repository) {
  $projectId = 10;

  // إنشاء 12 طلباً لتغطية منطق الـ pagination (10 في الصفحة)
  for ($i = 0; $i < 12; $i++) {
    return_requests::create([
      'project_id'    => $projectId,
      'order_id'      => 1,
      'order_item_id' => $i + 1,
      'user_id'       => 1,
      'status'        => 'pending'
    ]);
  }

  $results = $repository->getByProject($projectId);

  expect($results)->toBeInstanceOf(\Illuminate\Pagination\LengthAwarePaginator::class)
    ->and($results->count())->toBe(10)
    ->and($results->total())->toBe(12);
});
