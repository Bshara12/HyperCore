<?php

use App\Domains\Search\DTOs\SearchQueryDTO;

test('it initializes with all properties correctly', function () {
  $dto = new SearchQueryDTO(
    keyword: 'laravel testing',
    projectId: 10,
    language: 'ar',
    page: 2,
    perPage: 20,
    dataTypeSlug: 'blog',
    userId: 55,
    sessionId: 'session_xyz',
    debug: true
  );

  expect($dto->keyword)->toBe('laravel testing')
    ->and($dto->projectId)->toBe(10)
    ->and($dto->language)->toBe('ar')
    ->and($dto->page)->toBe(2)
    ->and($dto->perPage)->toBe(20)
    ->and($dto->dataTypeSlug)->toBe('blog')
    ->and($dto->userId)->toBe(55)
    ->and($dto->sessionId)->toBe('session_xyz')
    ->and($dto->debug)->toBeTrue();
});

test('it uses default values when optional fields are missing', function () {
  // تمرير فقط القيم الإجبارية
  $dto = new SearchQueryDTO(
    keyword: 'php',
    projectId: 1,
    language: 'en',
    page: 1,
    perPage: 15
  );

  expect($dto->dataTypeSlug)->toBeNull()
    ->and($dto->userId)->toBeNull()
    ->and($dto->sessionId)->toBeNull()
    ->and($dto->debug)->toBeFalse(); // القيمة الافتراضية
});
