<?php

use App\Domains\CMS\Read\DTOs\GetEntryDetailDTO;

test('it initializes properties correctly with all values', function () {
  $dto = new GetEntryDetailDTO(
    entryId: 55,
    language: 'ar',
    userId: 10
  );

  expect($dto->entryId)->toBe(55)
    ->and($dto->language)->toBe('ar')
    ->and($dto->userId)->toBe(10);
});

test('it handles null values for optional fields correctly', function () {
  // اختبار الحالة التي تكون فيها الحقول الاختيارية null
  $dto = new GetEntryDetailDTO(
    entryId: 99,
    language: null,
    userId: null
  );

  expect($dto->entryId)->toBe(99)
    ->and($dto->language)->toBeNull()
    ->and($dto->userId)->toBeNull();
});
