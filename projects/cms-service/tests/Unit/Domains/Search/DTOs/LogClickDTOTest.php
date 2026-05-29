<?php

use App\Domains\Search\DTOs\LogClickDTO;

test('it initializes with all properties correctly', function () {
  $dto = new LogClickDTO(
    projectId: 1,
    entryId: 100,
    dataTypeId: 5,
    resultPosition: 1,
    userId: 99,
    searchLogId: 500,
    sessionId: 'abc-123'
  );

  expect($dto->projectId)->toBe(1)
    ->and($dto->entryId)->toBe(100)
    ->and($dto->dataTypeId)->toBe(5)
    ->and($dto->resultPosition)->toBe(1)
    ->and($dto->userId)->toBe(99)
    ->and($dto->searchLogId)->toBe(500)
    ->and($dto->sessionId)->toBe('abc-123');
});

test('it initializes with mandatory properties and null defaults', function () {
  // اختبار الحالة التي لا يتم فيها تمرير القيم الاختيارية
  $dto = new LogClickDTO(
    projectId: 2,
    entryId: 200,
    dataTypeId: 10,
    resultPosition: 3
  );

  expect($dto->userId)->toBeNull()
    ->and($dto->searchLogId)->toBeNull()
    ->and($dto->sessionId)->toBeNull();
});
