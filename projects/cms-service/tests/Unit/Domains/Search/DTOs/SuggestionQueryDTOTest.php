<?php

use App\Domains\Search\DTOs\SuggestionQueryDTO;

test('it initializes with all properties correctly including userId', function () {
  $dto = new SuggestionQueryDTO(
    prefix: 'lar',
    projectId: 1,
    language: 'en',
    limit: 10,
    userId: 123
  );

  expect($dto->prefix)->toBe('lar')
    ->and($dto->projectId)->toBe(1)
    ->and($dto->language)->toBe('en')
    ->and($dto->limit)->toBe(10)
    ->and($dto->userId)->toBe(123);
});

test('it initializes with mandatory properties when userId is null', function () {
  $dto = new SuggestionQueryDTO(
    prefix: 'php',
    projectId: 5,
    language: 'ar',
    limit: 5,
    userId: null
  );

  expect($dto->userId)->toBeNull();
});
