<?php

use App\Domains\CMS\Services\Stock\DecrementStockService;
use App\Domains\CMS\Actions\Stock\DecrementStockAction;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
  $this->action = Mockery::mock(DecrementStockAction::class);
  $this->service = new DecrementStockService($this->action);
});

test('it executes decrement action within a database transaction', function () {
  $items = [
    ['product_id' => 1, 'quantity' => 2],
    ['product_id' => 2, 'quantity' => 5],
  ];

  // 1. نتوقع أن يتم استدعاء DB::transaction
  DB::shouldReceive('transaction')
    ->once()
    ->andReturnUsing(function ($callback) {
      // هذا ينفذ الكود الموجود داخل الـ closure في الـ Service
      return $callback();
    });

  // 2. نتوقع أن يتم استدعاء الـ Action داخل الـ transaction
  $this->action->shouldReceive('execute')
    ->once()
    ->with($items);

  // 3. التنفيذ
  $this->service->execute($items);
});
