<?php

namespace Tests\Feature\Domains\E_Commerce\Actions\ReturnRequest;

use App\Domains\E_Commerce\Actions\ReturnRequest\CreateReturnRequestAction;
use App\Domains\E_Commerce\DTOs\ReturnRequest\CreateReturnRequestDTO;
use App\Domains\E_Commerce\Repositories\Interfaces\Order\OrderItemRepositoryInterface;
use App\Domains\E_Commerce\Repositories\Interfaces\ReturnRequest\ReturnRequestRepositoryInterface;
use App\Events\SystemLogEvent;
use Illuminate\Support\Facades\Event;
use Mockery;

beforeEach(function () {
  $this->repo = Mockery::mock(ReturnRequestRepositoryInterface::class);
  $this->orderItemRepo = Mockery::mock(OrderItemRepositoryInterface::class);
  $this->action = new CreateReturnRequestAction($this->repo, $this->orderItemRepo);

  // وهمي للحدث لضمان عدم إرسال لوج حقيقي أثناء الفحص
  Event::fake([SystemLogEvent::class]);
});

it('creates a return request successfully', function () {
  // 1. تجهيز الـ DTO
  $dto = new CreateReturnRequestDTO(
    user_id: 1,
    order_id: 10,
    order_item_id: 100,
    description: 'Product damaged',
    quantity: 1,
    project_id: 1
  );

  // 2. محاكاة أن العنصر تم توصيله (Delivered)
  $this->orderItemRepo->shouldReceive('findById')
    ->once()
    ->with(100)
    ->andReturn((object)['id' => 100, 'status' => 'delivered']);

  // 3. محاكاة عدم وجود طلب إرجاع معلق سابق
  $this->repo->shouldReceive('findPendingByItem')
    ->once()
    ->with(100)
    ->andReturn(null);

  // 4. محاكاة عملية الإنشاء
  $this->repo->shouldReceive('create')
    ->once()
    ->andReturn(['id' => 50, 'status' => 'pending']);

  // التنفيذ
  $result = $this->action->execute($dto);

  // التحقق
  expect($result['id'])->toBe(50);
  Event::assertDispatched(SystemLogEvent::class);
});

it('throws an exception if the item is not delivered', function () {
  $dto = new CreateReturnRequestDTO(user_id: 1, order_id: 10, order_item_id: 100, description: '...', quantity: 1, project_id: 1);

  // محاكاة أن حالة المنتج 'shipped' وليس 'delivered'
  $this->orderItemRepo->shouldReceive('findById')
    ->andReturn((object)['status' => 'shipped']);

  expect(fn() => $this->action->execute($dto))
    ->toThrow(\Exception::class, 'Only delivered items can be returned');
});

it('throws an exception if a pending request already exists', function () {
  $dto = new CreateReturnRequestDTO(user_id: 1, order_id: 10, order_item_id: 100, description: '...', quantity: 1, project_id: 1);

  $this->orderItemRepo->shouldReceive('findById')
    ->andReturn((object)['status' => 'delivered']);

  // محاكاة وجود طلب سابق (ليس null)
  $this->repo->shouldReceive('findPendingByItem')
    ->andReturn((object)['id' => 49]);

  expect(fn() => $this->action->execute($dto))
    ->toThrow(\Exception::class, 'Request already exists');
});
