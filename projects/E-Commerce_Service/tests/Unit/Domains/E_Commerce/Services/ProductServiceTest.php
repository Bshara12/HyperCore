<?php

namespace Tests\Unit\Domains\E_Commerce\Services;

use App\Domains\E_Commerce\Services\ProductService;
use App\Domains\E_Commerce\Services\PricingService;
use Mockery;

beforeEach(function () {
  // إنشاء Mock للـ PricingService
  $this->pricingService = Mockery::mock(PricingService::class);

  $this->service = new ProductService(
    $this->pricingService
  );
});

afterEach(function () {
  Mockery::close();
});

it('proxies getProducts call to pricing service fromDataType', function () {
  $dataTypeSlug = 'electronics';
  $discountCode = 'SAVE20';
  $expectedOutput = [['id' => 1, 'name' => 'Phone', 'price' => 800]];

  // التوقع: يجب استدعاء fromDataType مع نفس المعاملات الممررة
  $this->pricingService->shouldReceive('fromDataType')
    ->once()
    ->with($dataTypeSlug, $discountCode)
    ->andReturn($expectedOutput);

  $result = $this->service->getProducts($dataTypeSlug, $discountCode);

  expect($result)->toBe($expectedOutput);
});

it('handles getProducts without a discount code', function () {
  $dataTypeSlug = 'clothes';

  $this->pricingService->shouldReceive('fromDataType')
    ->once()
    ->with($dataTypeSlug, null) // التأكد من تمرير null عند غياب الكود
    ->andReturn([]);

  $result = $this->service->getProducts($dataTypeSlug);

  expect($result)->toBeArray();
});
