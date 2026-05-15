<?php

namespace Tests\Feature\Http\Controllers;

use Tests\TestCase;
use App\Domains\E_Commerce\Services\ProductService;
use App\Http\Controllers\ProductController;
use Mockery\MockInterface;
use Illuminate\Http\Request;
use Illuminate\Foundation\Testing\WithoutMiddleware;
use PHPUnit\Framework\Attributes\Test;

class ProductControllerTest extends TestCase
{
  use WithoutMiddleware;

  private MockInterface $productServiceMock;

  protected function setUp(): void
  {
    parent::setUp();
    // إنشاء Mock للخدمة
    $this->productServiceMock = $this->mock(ProductService::class);
  }

  #[Test]
  public function it_can_get_products_with_datatype_and_code()
  {
    // 1. المعطيات
    $dataTypeSlug = 'electronics';
    $code = 'SALE20';
    $mockProducts = [
      ['id' => 1, 'name' => 'Laptop', 'price' => 1000],
      ['id' => 2, 'name' => 'Phone', 'price' => 500]
    ];

    // 2. توقع استدعاء الخدمة بالمعاملات الصحيحة
    $this->productServiceMock
      ->shouldReceive('getProducts')
      ->once()
      ->with($dataTypeSlug, $code)
      ->andReturn($mockProducts);

    // 3. بناء الـ Request ومحاكاة المدخلات
    $request = Request::create("/api/products/{$dataTypeSlug}", 'GET', [
      'code' => $code
    ]);

    // 4. تنفيذ التابع
    $controller = new ProductController($this->productServiceMock);
    $response = $controller->index($dataTypeSlug, $request);

    // 5. التحقق من تطابق النتيجة
    $this->assertEquals($mockProducts, $response);
  }
}
