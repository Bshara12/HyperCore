<?php

use App\Domains\Search\DTOs\PopularSearchResultDTO;
use App\Domains\Search\DTOs\PopularSearchItemDTO;

test('it initializes properties correctly', function () {
  $trendingItem = new PopularSearchItemDTO('laravel', 10, 0.95, 'up', '30d');

  $dto = new PopularSearchResultDTO(
    trending: [$trendingItem],
    popular: [],
    window: '30d',
    actualWindowUsed: '30d',
    fallbackApplied: false,
    source: 'redis',
    tookMs: 15.678
  );

  expect($dto->trending)->toHaveCount(1)
    ->and($dto->source)->toBe('redis')
    ->and($dto->tookMs)->toBe(15.678);
});

test('toArray converts deeply nested structures and formats values correctly', function () {
  // تجهيز بيانات فرعية
  $trendingItem = new PopularSearchItemDTO('laravel', 10, 0.95432, 'up', '30d');
  $popularItem = new PopularSearchItemDTO('php', 20, 0.88123, 'stable', '30d');

  $dto = new PopularSearchResultDTO(
    trending: [$trendingItem],
    popular: [$popularItem],
    window: '30d',
    actualWindowUsed: '30d',
    fallbackApplied: true,
    source: 'database',
    tookMs: 45.126 // يجب أن يتم تقريبه لـ 45.13
  );

  $array = $dto->toArray();

  // التحقق من القيم الرئيسية
  expect($array['took_ms'])->toBe(45.13)
    ->and($array['fallback_applied'])->toBeTrue()
    ->and($array['source'])->toBe('database');

  // التحقق من التحويل المتداخل (Recursive Mapping)
  expect($array['trending'][0]['keyword'])->toBe('laravel')
    ->and($array['trending'][0]['score'])->toBe(0.9543) // تم تقريبه داخل الـ Item
    ->and($array['popular'][0]['keyword'])->toBe('php');
});
