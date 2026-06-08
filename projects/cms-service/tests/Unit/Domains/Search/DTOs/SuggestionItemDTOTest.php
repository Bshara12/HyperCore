<?php

use App\Domains\Search\DTOs\SuggestionItemDTO;

test('it initializes properties correctly', function () {
  $dto = new SuggestionItemDTO(
    keyword: 'laravel',
    searchCount: 1500,
    score: 0.99,
    highlight: '**lar**avel'
  );

  expect($dto->keyword)->toBe('laravel')
    ->and($dto->searchCount)->toBe(1500)
    ->and($dto->score)->toBe(0.99)
    ->and($dto->highlight)->toBe('**lar**avel');
});

test('toArray returns correctly formatted array', function () {
  $dto = new SuggestionItemDTO(
    keyword: 'php',
    searchCount: 500,
    score: 0.85,
    highlight: 'p**hp**'
  );

  $array = $dto->toArray();

  expect($array)->toBe([
    'keyword' => 'php',
    'search_count' => 500,
    'score' => 0.85,
    'highlight' => 'p**hp**',
  ]);
});
