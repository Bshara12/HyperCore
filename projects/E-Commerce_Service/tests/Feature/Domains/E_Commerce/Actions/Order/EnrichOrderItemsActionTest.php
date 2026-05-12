<?php

use App\Domains\E_Commerce\Actions\Order\EnrichOrderItemsAction;
use App\Domains\E_Commerce\Actions\Pricing\EnrichEntriesWithPricesAction;
use App\Domains\E_Commerce\Actions\Pricing\FetchEntriesByIdsAction;
use Illuminate\Support\Fluent;

beforeEach(function () {
  $this->pricingAction = Mockery::mock(EnrichEntriesWithPricesAction::class);
  $this->action = new EnrichOrderItemsAction($this->pricingAction);
});

it('enriches order items with cms entries correctly', function () {
  // 1. تجهيز بيانات الطلبات (Orders)
  $orderItem = new Fluent(['product_id' => 101, 'entry' => null]);
  $order = new Fluent(['items' => [$orderItem]]);
  $orders = [$order];

  // 2. معالجة الـ app() call داخل الـ Action
  // بما أن الكود يستخدم app(...) فنحن بحاجة لعمل Bind للموك في الـ Container
  $fetchEntriesMock = Mockery::mock(FetchEntriesByIdsAction::class);
  app()->instance(FetchEntriesByIdsAction::class, $fetchEntriesMock);

  // 3. تحديد التوقعات (Expectations)
  $rawEntries = [['id' => 101, 'title' => 'Product A']];
  $fetchEntriesMock->shouldReceive('execute')
    ->once()
    ->with([101])
    ->andReturn($rawEntries);

  $enrichedEntries = [['id' => 101, 'title' => 'Product A', 'final_price' => 50]];
  $this->pricingAction->shouldReceive('execute')
    ->once()
    ->with($rawEntries)
    ->andReturn($enrichedEntries);

  // 4. التنفيذ
  $result = $this->action->execute($orders);

  // 5. التحقق
  expect($result[0]->items[0]->entry)->not->toBeNull();
  expect($result[0]->items[0]->entry['final_price'])->toBe(50);
  expect($result[0]->items[0]->entry['id'])->toBe(101);
});

it('sets entry to null if not found in cms', function () {
  $orderItem = new Fluent(['product_id' => 999]);
  $orders = [new Fluent(['items' => [$orderItem]])];

  $fetchEntriesMock = Mockery::mock(FetchEntriesByIdsAction::class);
  app()->instance(FetchEntriesByIdsAction::class, $fetchEntriesMock);

  // إرجاع مصفوفة فارغة لمحاكاة عدم وجود المنتج
  $fetchEntriesMock->shouldReceive('execute')->andReturn([]);
  $this->pricingAction->shouldReceive('execute')->andReturn([]);

  $result = $this->action->execute($orders);

  expect($result[0]->items[0]->entry)->toBeNull();
});
