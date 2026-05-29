<?php

use App\Domains\CMS\Read\DTOs\EntryVersionDTO;

test('it initializes properties correctly with all values', function () {
  $snapshot = ['title' => 'v1', 'content' => 'data'];
  $createdAt = '2026-05-25 14:00:00';

  $dto = new EntryVersionDTO(
    id: 1,
    data_entry_id: 10,
    version_number: 2,
    created_by: 5,
    created_at: $createdAt,
    snapshot: $snapshot
  );

  expect($dto->id)->toBe(1)
    ->and($dto->data_entry_id)->toBe(10)
    ->and($dto->version_number)->toBe(2)
    ->and($dto->created_by)->toBe(5)
    ->and($dto->created_at)->toBe($createdAt)
    ->and($dto->snapshot)->toBe($snapshot);
});

test('it handles null values for optional fields correctly', function () {
  $dto = new EntryVersionDTO(
    id: 2,
    data_entry_id: 11,
    version_number: 1,
    created_by: null,
    created_at: '2026-05-25 12:00:00',
    snapshot: null
  );

  expect($dto->created_by)->toBeNull()
    ->and($dto->snapshot)->toBeNull();
});
