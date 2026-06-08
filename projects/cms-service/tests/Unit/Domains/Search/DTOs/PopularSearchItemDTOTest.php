<?php

use App\Domains\Search\DTOs\PopularSearchItemDTO;

test('it initializes properties correctly', function () {
  $dto = new PopularSearchItemDTO(
    keyword: 'laravel',
    count: 100,
    score: 0.854321,
    trend: 'upward',
    windowUsed: 'last_7_days'
  );

  expect($dto->keyword)->toBe('laravel')
    ->and($dto->count)->toBe(100)
    ->and($dto->score)->toBe(0.854321)
    ->and($dto->trend)->toBe('upward')
    ->and($dto->windowUsed)->toBe('last_7_days');
});

test('toArray returns correctly formatted array', function () {
  $dto = new PopularSearchItemDTO(
    keyword: 'php',
    count: 50,
    score: 0.1234567, // رقم عشري طويل ليتم تقريبه
    trend: null,
    windowUsed: 'today'
  );

  $array = $dto->toArray();

  expect($array)->toBe([
    'keyword' => 'php',
    'count' => 50,
    'score' => 0.1235, // تم تقريبه لـ 4 خانات
    'trend' => null,
    'window_used' => 'today' // تم تحويل الاسم لـ snake_case
  ]);
});
