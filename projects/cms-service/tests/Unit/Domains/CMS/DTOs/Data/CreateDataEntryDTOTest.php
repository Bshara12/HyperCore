<?php

use App\Domains\CMS\DTOs\Data\CreateDataEntryDTO;
use App\Domains\CMS\Requests\DataEntryRequest;

test('it correctly transforms data entries into DTO with normalized values', function () {
  // 1. تحضير Request يحتوي على قيم بسيطة (تحتاج تحويل) وقيم مصفوفات (لا تحتاج)
  $request = DataEntryRequest::create('/mock', 'POST', [
    'values' => [
      'title' => 'My Post',           // يحتاج تحويل لـ [null => 'My Post']
      'tags' => ['php', 'laravel'],   // لا يحتاج تحويل
    ],
    'seo' => ['meta_title' => 'SEO Title'],
    'status' => 'published'
  ]);

  // 2. تنفيذ التحويل
  $dto = CreateDataEntryDTO::fromRequest($request);

  // 3. التحقق من النتائج
  expect($dto->values['title'])->toBe([null => 'My Post']) // تحقق من التحويل
    ->and($dto->values['tags'])->toBe(['php', 'laravel']) // تحقق من بقاء المصفوفة كما هي
    ->and($dto->seo)->toBe(['meta_title' => 'SEO Title'])
    ->and($dto->status)->toBe('published');
});

test('it uses default values when optional fields are missing', function () {
  $request = DataEntryRequest::create('/mock', 'POST', [
    'values' => ['content' => 'Hello World'],
  ]);

  $dto = CreateDataEntryDTO::fromRequest($request);

  expect($dto->values['content'])->toBe([null => 'Hello World'])
    ->and($dto->status)->toBe('draft') // القيمة الافتراضية
    ->and($dto->seo)->toBeNull()
    ->and($dto->scheduled_at)->toBeNull();
});
