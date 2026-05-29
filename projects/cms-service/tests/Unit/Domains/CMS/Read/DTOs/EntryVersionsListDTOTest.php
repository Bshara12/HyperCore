<?php

use App\Domains\CMS\Read\DTOs\EntryVersionsListDTO;
use App\Domains\CMS\Read\DTOs\EntryVersionDTO;

test('it initializes properties correctly including the items array', function () {
  // 1. تحضير كائنات فرعية (EntryVersionDTO)
  $version1 = new EntryVersionDTO(1, 101, 1, 5, '2026-05-25 10:00:00');
  $version2 = new EntryVersionDTO(2, 101, 2, 5, '2026-05-25 11:00:00');

  $items = [$version1, $version2];

  // 2. إنشاء الـ List DTO
  $dto = new EntryVersionsListDTO(
    total: 2,
    page: 1,
    per_page: 15,
    items: $items
  );

  // 3. التحقق من صحة البيانات
  expect($dto->total)->toBe(2)
    ->and($dto->page)->toBe(1)
    ->and($dto->per_page)->toBe(15)
    ->and($dto->items)->toHaveCount(2)
    ->and($dto->items[0])->toBe($version1)
    ->and($dto->items[1])->toBe($version2);
});
