<?php

use App\Domains\Search\DTOs\SuggestionResultDTO;
use App\Domains\Search\DTOs\SuggestionItemDTO;

test('it initializes properties correctly', function () {
  $dto = new SuggestionResultDTO(
    prefix: 'lar',
    items: [],
    source: 'db',
    tookMs: 5.678
  );

  expect($dto->prefix)->toBe('lar')
    ->and($dto->source)->toBe('db')
    ->and($dto->tookMs)->toBe(5.678);
});

test('toArray returns correctly formatted array with mapped items and rounded time', function () {
  // 1. محاكاة (Mock) لعنصر الاقتراح للتأكد من استدعاء toArray الخاص به
  $mockItem = Mockery::mock(SuggestionItemDTO::class);
  $mockItem->shouldReceive('toArray')
    ->once()
    ->andReturn([
      'keyword' => 'laravel',
      'search_count' => 100,
      'score' => 0.9,
      'highlight' => '**lar**avel'
    ]);

  $dto = new SuggestionResultDTO(
    prefix: 'lar',
    items: [$mockItem],
    source: 'cache',
    tookMs: 12.3456 // سيتم تقريبه إلى 12.35
  );

  $array = $dto->toArray();

  // 2. التحقق من الهيكلية والتقريب
  expect($array)->toBe([
    'prefix' => 'lar',
    'took_ms' => 12.35,
    'source' => 'cache',
    'results' => [
      [
        'keyword' => 'laravel',
        'search_count' => 100,
        'score' => 0.9,
        'highlight' => '**lar**avel'
      ]
    ]
  ]);
});
