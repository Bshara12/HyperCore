<?php

namespace Tests\Feature\Domains\E_Commerce\Actions\ReturnRequest;

use App\Domains\E_Commerce\Actions\ReturnRequest\UpdateReturnRequestAction;
use App\Domains\E_Commerce\DTOs\ReturnRequest\UpdateReturnRequestDTO;
use App\Domains\E_Commerce\Repositories\Interfaces\Order\OrderItemRepositoryInterface;
use App\Domains\E_Commerce\Repositories\Interfaces\Order\OrderRepositoryInterface;
use App\Domains\E_Commerce\Repositories\Interfaces\ReturnRequest\ReturnRequestRepositoryInterface;
use App\Events\SystemLogEvent;
use Illuminate\Support\Facades\Event;
use Mockery;

beforeEach(function () {
  $this->repo = Mockery::mock(ReturnRequestRepositoryInterface::class);
  $this->orderItemRepo = Mockery::mock(OrderItemRepositoryInterface::class);
  $this->orderRepo = Mockery::mock(OrderRepositoryInterface::class);

  $this->action = new UpdateReturnRequestAction(
    $this->repo,
    $this->orderItemRepo,
    $this->orderRepo
  );

  Event::fake([SystemLogEvent::class]);
});

it('updates return request to approved and sets order to partially_returned', function () {
  $dto = new UpdateReturnRequestDTO(id: 1, status: 'approved');

  // 1. محاكاة وجود طلب إرجاع معلق
  $request = (object)[
    'id' => 1,
    'status' => 'pending',
    'order_id' => 10,
    'order_item_id' => 100
  ];
  $this->repo->shouldReceive('findById')->with(1)->andReturn($request);
  $this->repo->shouldReceive('update')->once();

  // 2. محاكاة تحديث الـ Item
  $this->orderItemRepo->shouldReceive('updateStatus')->once()->with(100, 'returned');

  // 3. محاكاة وجود عنصرين في الطلب (واحد فقط تم إرجاعه)
  $items = collect([
    (object)['id' => 100, 'status' => 'returned'], // العنصر الحالي
    (object)['id' => 101, 'status' => 'delivered'], // عنصر آخر لم يرجع بعد
  ]);
  $this->orderItemRepo->shouldReceive('findByOrderId')->with(10)->andReturn($items);

  // 4. التوقع: الحالة ستكون partially_returned لأن 1/2 رجعوا
  $this->orderRepo->shouldReceive('updateStatus')->once()->with(10, 'partially_returned');

  $result = $this->action->execute($dto);

  expect($result->id)->toBe(1);
  Event::assertDispatched(SystemLogEvent::class);
});

it('updates order to returned when all items are returned', function () {
  $dto = new UpdateReturnRequestDTO(id: 1, status: 'approved');
  $request = (object)['id' => 1, 'status' => 'pending', 'order_id' => 10, 'order_item_id' => 100];

  $this->repo->shouldReceive('findById')->andReturn($request);
  $this->repo->shouldReceive('update');
  $this->orderItemRepo->shouldReceive('updateStatus');

  // محاكاة أن كل العناصر أصبحت 'returned'
  $items = collect([
    (object)['id' => 100, 'status' => 'returned'],
  ]);
  $this->orderItemRepo->shouldReceive('findByOrderId')->andReturn($items);

  // التوقع: الحالة ستكون returned لأن الكل رجع
  $this->orderRepo->shouldReceive('updateStatus')->once()->with(10, 'returned');

  $this->action->execute($dto);
});

it('throws exception if request is not pending or not found', function () {
  $dto = new UpdateReturnRequestDTO(id: 1, status: 'approved');

  // محاكاة طلب حالته مقبولة مسبقاً (ليس pending)
  $this->repo->shouldReceive('findById')->andReturn((object)['status' => 'approved']);

  expect(fn() => $this->action->execute($dto))
    ->toThrow(\Exception::class, 'Invalid request');
});
