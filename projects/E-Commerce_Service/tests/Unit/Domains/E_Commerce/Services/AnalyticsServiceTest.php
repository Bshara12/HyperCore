<?php

namespace Tests\Unit\Domains\E_Commerce\Services;

use App\Domains\E_Commerce\Services\AnalyticsService;
use App\Domains\E_Commerce\Analytics\Actions\GetOffersAnalyticsAction;
use App\Domains\E_Commerce\Analytics\Actions\GetReturnsAnalyticsAction;
use App\Domains\E_Commerce\Analytics\Actions\GetSalesSummaryAction;
use App\Domains\E_Commerce\Analytics\Actions\GetSalesTrendAction;
use App\Domains\E_Commerce\Analytics\Actions\GetTopCustomersAction;
use App\Domains\E_Commerce\Analytics\Actions\GetTopProductsAction;
use App\Domains\E_Commerce\Analytics\DTOs\AnalyticsFilterDTO;
use Mockery;

beforeEach(function () {
  $this->offersAction    = Mockery::mock(GetOffersAnalyticsAction::class);
  $this->returnsAction   = Mockery::mock(GetReturnsAnalyticsAction::class);
  $this->summaryAction   = Mockery::mock(GetSalesSummaryAction::class);
  $this->trendAction     = Mockery::mock(GetSalesTrendAction::class);
  $this->customersAction = Mockery::mock(GetTopCustomersAction::class);
  $this->productsAction  = Mockery::mock(GetTopProductsAction::class);

  $this->service = new AnalyticsService(
    $this->offersAction,
    $this->returnsAction,
    $this->summaryAction,
    $this->trendAction,
    $this->customersAction,
    $this->productsAction
  );

  // ✅ الحل الصحيح: تمرير القيم المطلوبة مباشرة للـ Constructor بالترتيب الصحيح
  $this->dto = new AnalyticsFilterDTO(
    from: '2026-01-01',
    to: '2026-05-12',
    period: 'monthly',
    projectId: 1,
    limit: 10
  );
});

afterEach(function () {
  Mockery::close();
});

it('calls getSalesSummary and returns the result from action', function () {
  $this->summaryAction->shouldReceive('execute')
    ->once()
    ->with($this->dto)
    ->andReturn(['total' => 1000]);

  expect($this->service->getSalesSummary($this->dto))->toBe(['total' => 1000]);
});

it('calls getSalesTrend and returns the result from action', function () {
  $this->trendAction->shouldReceive('execute')
    ->once()
    ->with($this->dto)
    ->andReturn(['trend' => 'up']);

  expect($this->service->getSalesTrend($this->dto))->toBe(['trend' => 'up']);
});

it('calls getTopCustomers and returns the result from action', function () {
  $this->customersAction->shouldReceive('execute')
    ->once()
    ->with($this->dto)
    ->andReturn(['customer_1']);

  expect($this->service->getTopCustomers($this->dto))->toBe(['customer_1']);
});

it('calls getTopProducts and returns the result from action', function () {
  $this->productsAction->shouldReceive('execute')
    ->once()
    ->with($this->dto)
    ->andReturn(['product_a']);

  expect($this->service->getTopProducts($this->dto))->toBe(['product_a']);
});

it('calls getReturnsAnalytics and returns the result from action', function () {
  $this->returnsAction->shouldReceive('execute')
    ->once()
    ->with($this->dto)
    ->andReturn(['returns' => 5]);

  expect($this->service->getReturnsAnalytics($this->dto))->toBe(['returns' => 5]);
});

it('calls getOffersAnalytics and returns the result from action', function () {
  $this->offersAction->shouldReceive('execute')
    ->once()
    ->with($this->dto)
    ->andReturn(['offers_used' => 10]);

  expect($this->service->getOffersAnalytics($this->dto))->toBe(['offers_used' => 10]);
});
