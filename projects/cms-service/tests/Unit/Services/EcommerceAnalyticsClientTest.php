<?php

use App\Services\EcommerceAnalyticsClient;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Config;

beforeEach(function () {
  Config::set('services.ecommerce_service.url', 'https://api.ecommerce-service.test');
  $this->client = new EcommerceAnalyticsClient();
});

afterEach(function () {
  Http::preventStrayRequests(false);
});

test('it fetches summary analytics successfully and extracts data key', function () {
  Http::fake([
    '*/ecommerce/analytics/summary*' => Http::response([
      'data' => ['total_sales' => 5000]
    ], 200),
  ]);

  $result = $this->client->getSummary('fake-token', 'project-1', ['range' => 'daily']);
  expect($result)->toBe(['total_sales' => 5000]);
});

test('it fetches sales trend analytics successfully without data key', function () {
  Http::fake([
    '*/ecommerce/analytics/*' => Http::response([
      'sales' => [100, 200, 300]
    ], 200),
  ]);

  $result = $this->client->getSalesTrend('fake-token', 'project-1');
  expect($result)->toBe(['sales' => [100, 200, 300]]);
});

test('it fetches top products analytics successfully', function () {
  Http::fake([
    '*/ecommerce/analytics/*' => Http::response([
      'data' => ['product' => 'Laptop']
    ], 200),
  ]);

  $result = $this->client->getTopProducts('fake-token', 'project-1');
  expect($result)->toBe(['product' => 'Laptop']);
});

test('it fetches offers analytics successfully', function () {
  Http::fake([
    '*/ecommerce/analytics/*' => Http::response([
      'data' => ['active_offers' => 3]
    ], 200),
  ]);

  $result = $this->client->getOffersAnalytics('fake-token', 'project-1');
  expect($result)->toBe(['active_offers' => 3]);
});

test('it fetches top customers analytics successfully', function () {
  Http::fake([
    '*/ecommerce/analytics/*' => Http::response([
      'data' => ['customer' => 'John Doe']
    ], 200),
  ]);

  $result = $this->client->getTopCustomers('fake-token', 'project-1');
  expect($result)->toBe(['customer' => 'John Doe']);
});

test('it fetches returns analytics successfully', function () {
  Http::fake([
    '*/ecommerce/analytics/*' => Http::response([
      'data' => ['returns_count' => 2]
    ], 200),
  ]);

  $result = $this->client->getReturnsAnalytics('fake-token', 'project-1');
  expect($result)->toBe(['returns_count' => 2]);
});

test('it throws an exception when the ecommerce service returns an error status', function () {
  Http::fake([
    '*/ecommerce/analytics/*' => Http::response('Internal Server Error', 500),
  ]);

  expect(fn() => $this->client->getSummary('fake-token', 'project-1'))
    ->toThrow(Exception::class, 'Ecommerce Service Error: Internal Server Error');
});
