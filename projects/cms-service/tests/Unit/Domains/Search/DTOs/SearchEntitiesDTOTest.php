<?php

use App\Domains\Search\DTOs\SearchEntitiesDTO;

test('it creates an empty instance correctly', function () {
  $dto = SearchEntitiesDTO::empty();

  expect($dto->hasEntities)->toBeFalse()
    ->and($dto->attributes)->toBeEmpty()
    ->and($dto->product)->toBeNull();
});

test('it creates a fully populated object and converts to array correctly', function () {
  $data = [
    'product' => 'iphone 15',
    'brand' => 'apple',
    'location' => 'romania',
    'model' => '15 pro max',
    'minPrice' => 500.0,
    'maxPrice' => 1000.0,
    'attributes' => ['color' => 'red', 'size' => '6.1'],
    'hasEntities' => true,
  ];

  $dto = new SearchEntitiesDTO(
    product: $data['product'],
    brand: $data['brand'],
    location: $data['location'],
    model: $data['model'],
    minPrice: $data['minPrice'],
    maxPrice: $data['maxPrice'],
    attributes: $data['attributes'],
    hasEntities: $data['hasEntities']
  );

  $array = $dto->toArray();

  expect($array)->toBe([
    'product' => 'iphone 15',
    'brand' => 'apple',
    'location' => 'romania',
    'model' => '15 pro max',
    'min_price' => 500.0,
    'max_price' => 1000.0,
    'attributes' => ['color' => 'red', 'size' => '6.1'],
    'has_entities' => true,
  ]);
});
