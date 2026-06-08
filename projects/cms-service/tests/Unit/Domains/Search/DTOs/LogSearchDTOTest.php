<?php

use App\Domains\Search\DTOs\LogSearchDTO;

test('it initializes with all properties correctly', function () {
  $dto = new LogSearchDTO(
    projectId: 1,
    keyword: 'laravel',
    language: 'en',
    resultsCount: 50,
    detectedIntent: 'search_documentation',
    intentConfidence: 0.98,
    userId: 123,
    sessionId: 'sess_abc'
  );

  expect($dto->projectId)->toBe(1)
    ->and($dto->keyword)->toBe('laravel')
    ->and($dto->language)->toBe('en')
    ->and($dto->resultsCount)->toBe(50)
    ->and($dto->detectedIntent)->toBe('search_documentation')
    ->and($dto->intentConfidence)->toBe(0.98)
    ->and($dto->userId)->toBe(123)
    ->and($dto->sessionId)->toBe('sess_abc');
});

test('it initializes with mandatory properties and null defaults', function () {
  // اختبار الحالة التي يتم فيها تمرير الحقول الإجبارية فقط
  $dto = new LogSearchDTO(
    projectId: 2,
    keyword: 'php',
    language: 'ar',
    resultsCount: 10
  );

  expect($dto->projectId)->toBe(2)
    ->and($dto->userId)->toBeNull()
    ->and($dto->sessionId)->toBeNull()
    ->and($dto->detectedIntent)->toBeNull()
    ->and($dto->intentConfidence)->toBeNull();
});
