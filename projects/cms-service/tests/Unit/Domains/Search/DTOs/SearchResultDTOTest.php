<?php

use App\Domains\Search\DTOs\SearchResultDTO;
use App\Domains\Search\DTOs\SearchResultItemDTO;

test('it initializes properties correctly', function () {
  $dto = new SearchResultDTO(
    keyword: 'iphone',
    total: 100,
    page: 1,
    perPage: 10,
    lastPage: 10,
    items: [],
    aiEnhanced: true,
    aiQuery: 'show apple products',
    keyboardFixed: true,
    keyboardQuery: 'iphone 15'
  );

  expect($dto->keyword)->toBe('iphone')
    ->and($dto->aiEnhanced)->toBeTrue()
    ->and($dto->aiQuery)->toBe('show apple products')
    ->and($dto->keyboardFixed)->toBeTrue();
});

test('toArray returns correctly structured array', function () {
  // 1. إنشاء عنصر وهمي (Mock) للـ Item
  // ملاحظة: نفترض أن SearchResultItemDTO لديه ميثود toArray
  $mockItem = Mockery::mock(SearchResultItemDTO::class);
  $mockItem->shouldReceive('toArray')
    ->once()
    ->andReturn(['id' => 1, 'title' => 'iPhone 15']);

  $dto = new SearchResultDTO(
    keyword: 'iphone',
    total: 100,
    page: 2,
    perPage: 10,
    lastPage: 10,
    items: [$mockItem],
    aiEnhanced: false,
    aiQuery: null,
    keyboardFixed: false,
    keyboardQuery: null
  );

  $array = $dto->toArray();

  // 2. التحقق من الهيكلية
  expect($array)->toBe([
    'keyword' => 'iphone',
    'ai_enhanced' => false,
    'ai_query' => null,
    'keyboard_fixed' => false,
    'keyboard_query' => null,
    'meta' => [
      'total' => 100,
      'page' => 2,
      'per_page' => 10,
      'last_page' => 10,
    ],
    'results' => [
      ['id' => 1, 'title' => 'iPhone 15']
    ]
  ]);
});
