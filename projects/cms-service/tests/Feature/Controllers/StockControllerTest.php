<?php

namespace Tests\Feature\Http\Controllers;

use Tests\TestCase;
use App\Http\Controllers\StockController;
use App\Domains\CMS\Services\Stock\DecrementStockService;
use App\Domains\CMS\Requests\StockRequest;
use Mockery;
use PHPUnit\Framework\Attributes\Test;

class StockControllerTest extends TestCase
{
  protected function tearDown(): void
  {
    Mockery::close();
    parent::tearDown();
  }

  #[Test]
  public function it_decrements_stock_successfully()
  {
    // 1. محاكاة الـ Request وتوقع دالة items()
    $request = Mockery::mock(StockRequest::class);
    $items = [
      ['product_id' => 1, 'quantity' => 5],
      ['product_id' => 2, 'quantity' => 1]
    ];
    $request->shouldReceive('items')->once()->andReturn($items);

    // 2. محاكاة الخدمة وتوقع استدعاء execute بالبيانات الصحيحة
    $service = Mockery::mock(DecrementStockService::class);
    $service->shouldReceive('execute')
      ->once()
      ->with($items); // التأكد من أن الخدمة استلمت نفس المصفوفة

    // 3. التنفيذ
    $controller = new StockController();
    $response = $controller->decrement($request, $service);

    // 4. التأكيد
    $this->assertEquals(200, $response->getStatusCode());
    $this->assertEquals(
      ['message' => 'Stock updated successfully'],
      $response->getData(true)
    );
  }
}
