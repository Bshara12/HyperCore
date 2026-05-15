<?php

namespace Tests\Unit\Domains\E_Commerce\Services;

use App\Domains\E_Commerce\Services\PricingService;
use App\Domains\E_Commerce\Actions\Pricing\EnrichEntriesWithPricesAction;
use App\Domains\E_Commerce\Actions\Pricing\FetchEntriesByIdsAction;
use App\Services\CMS\CMSApiClient;
use Mockery;

beforeEach(function () {
  $this->fetchEntries = Mockery::mock(FetchEntriesByIdsAction::class);
  $this->pricing = Mockery::mock(EnrichEntriesWithPricesAction::class);
  $this->cms = Mockery::mock(CMSApiClient::class);

  $this->service = new PricingService(
    $this->fetchEntries,
    $this->pricing,
    $this->cms
  );
});

afterEach(function () {
  Mockery::close();
});

it('calculates pricing for specific entry IDs', function () {
  $ids = [1, 2];
  $entries = [['id' => 1], ['id' => 2]];
  $enriched = [['id' => 1, 'price' => 100], ['id' => 2, 'price' => 200]];

  $this->fetchEntries->shouldReceive('execute')->once()->with($ids)->andReturn($entries);
  $this->pricing->shouldReceive('execute')->once()->with($entries)->andReturn($enriched);

  $result = $this->service->calculate($ids);

  expect($result)->toBe($enriched);
});

it('calculates pricing from a collection slug', function () {
  $slug = 'summer-collection';
  $collectionResponse = [
    'items' => [
      ['entry' => ['id' => 10, 'name' => 'Product A']],
      ['entry' => ['id' => 20, 'name' => 'Product B']],
    ]
  ];

  // البيانات المتوقعة بعد عملية الـ pluck
  $expectedEntries = [
    ['id' => 10, 'name' => 'Product A'],
    ['id' => 20, 'name' => 'Product B'],
  ];

  $this->cms->shouldReceive('getCollectionBySlug')->once()->with($slug)->andReturn($collectionResponse);
  $this->pricing->shouldReceive('execute')->once()->with($expectedEntries)->andReturn(['enriched_data']);

  $result = $this->service->fromCollection($slug);

  expect($result)->toBe(['enriched_data']);
});

it('calculates pricing from a data type slug', function () {
  $dataTypeSlug = 'products';
  $entries = [['id' => 100]];

  $this->cms->shouldReceive('getEntriesByDataType')->once()->with($dataTypeSlug)->andReturn($entries);
  $this->pricing->shouldReceive('execute')->once()->with($entries)->andReturn(['enriched_data']);

  $result = $this->service->fromDataType($dataTypeSlug);

  expect($result)->toBe(['enriched_data']);
});
