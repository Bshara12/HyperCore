<?php

use App\Domains\CMS\DTOs\CreateDataEntryDTO;

test('it initializes properties correctly with provided values', function () {
  $values = [
    1 => ['en' => 'Title', 'ar' => 'العنوان'],
    2 => ['en' => 'Content', 'ar' => 'المحتوى'],
  ];

  $dto = new CreateDataEntryDTO(
    projectId: 1,
    dataTypeId: 5,
    status: 'published',
    createdBy: 10,
    values: $values
  );

  expect($dto->projectId)->toBe(1)
    ->and($dto->dataTypeId)->toBe(5)
    ->and($dto->status)->toBe('published')
    ->and($dto->createdBy)->toBe(10)
    ->and($dto->values)->toBe($values);
});

test('it uses default empty array for values when not provided', function () {
  // اختبار القيم الافتراضية
  $dto = new CreateDataEntryDTO(
    projectId: 1,
    dataTypeId: 5,
    status: 'draft',
    createdBy: 10
  );

  expect($dto->values)->toBeEmpty();
});
