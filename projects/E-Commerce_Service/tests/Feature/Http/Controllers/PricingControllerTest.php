<?php

namespace Tests\Feature\Http\Controllers;

use Tests\TestCase;
use App\Domains\E_Commerce\Services\PricingService;
use App\Http\Controllers\PricingController;
use App\Domains\E_Commerce\Requests\CalculatePricingRequest;
use Mockery\MockInterface;
use Illuminate\Foundation\Testing\WithoutMiddleware;
use PHPUnit\Framework\Attributes\Test;

class PricingControllerTest extends TestCase
{
  use WithoutMiddleware;

  private MockInterface $pricingServiceMock;

  protected function setUp(): void
  {
    parent::setUp();
    $this->pricingServiceMock = $this->mock(PricingService::class);
  }

  #[Test]
  public function it_can_calculate_pricing_successfully()
  {
    // 1. تجهيز البيانات (المدخلات والمخرجات المتوقعة)
    $entryIds = [101, 102, 103];
    $mockResult = [
      'subtotal' => 300,
      'discount' => 50,
      'total' => 250
    ];

    // 2. توقع استدعاء الخدمة مع المعاملات الصحيحة
    $this->pricingServiceMock
      ->shouldReceive('calculate')
      ->once()
      ->with($entryIds)
      ->andReturn($mockResult);

    // 3. بناء الـ Request
    $request = CalculatePricingRequest::create('/api/pricing/calculate', 'POST', [
      'entry_ids' => $entryIds
    ]);

    // حقن الـ Request في الحاوية لضمان عمل الـ Validation (إن وجد)
    $this->app->instance(CalculatePricingRequest::class, $request);

    // 4. تنفيذ التابع
    $controller = new PricingController($this->pricingServiceMock);
    $response = $controller->calculate($request);

    // 5. التحقق (بما أن التابع يعيد استجابة الخدمة مباشرة)
    $this->assertEquals($mockResult, $response);
  }
}
