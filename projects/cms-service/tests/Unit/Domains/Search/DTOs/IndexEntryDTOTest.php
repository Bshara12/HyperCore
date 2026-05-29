<?php

use App\Domains\Search\DTOs\IndexEntryDTO;

test('it maps all array values correctly to DTO', function () {
  $data = [
    'entry_id' => 101,
    'data_type_id' => 5,
    'project_id' => 20,
    'language' => 'ar',
    'title' => 'AI Title',
    'content' => 'AI Content Body',
    'meta' => ['version' => 1.0],
    'status' => 'archived',
    'published_at' => '2026-05-26 12:00:00'
  ];

  $dto = IndexEntryDTO::fromArray($data);

  expect($dto->entryId)->toBe(101)
    ->and($dto->dataTypeId)->toBe(5)
    ->and($dto->projectId)->toBe(20)
    ->and($dto->language)->toBe('ar')
    ->and($dto->title)->toBe('AI Title')
    ->and($dto->content)->toBe('AI Content Body')
    ->and($dto->meta)->toBe(['version' => 1.0])
    ->and($dto->status)->toBe('archived')
    ->and($dto->publishedAt)->toBe('2026-05-26 12:00:00');
});

test('it applies default values when optional fields are missing', function () {
  // إرسال فقط الحقول الإجبارية
  $data = [
    'entry_id' => 50,
    'data_type_id' => 2,
    'project_id' => 10,
  ];

  $dto = IndexEntryDTO::fromArray($data);

  expect($dto->language)->toBe('en')      // القيمة الافتراضية
    ->and($dto->status)->toBe('published') // القيمة الافتراضية
    ->and($dto->title)->toBeNull()
    ->and($dto->content)->toBeNull()
    ->and($dto->meta)->toBeNull()
    ->and($dto->publishedAt)->toBeNull();
});
