<?php

use App\Domains\Search\DTOs\SearchResultItemDTO;

test('it initializes properties correctly', function () {
  $dto = new SearchResultItemDTO(
    entryId: 101,
    dataTypeId: 5,
    projectId: 20,
    language: 'ar',
    title: 'مقالة تجريبية',
    snippet: 'هذا مقتطف من المحتوى...',
    status: 'published',
    score: 0.985,
    publishedAt: '2026-05-26 10:00:00'
  );

  expect($dto->entryId)->toBe(101)
    ->and($dto->score)->toBe(0.985)
    ->and($dto->status)->toBe('published');
});

test('toArray returns correctly formatted array with snake_case keys', function () {
  $dto = new SearchResultItemDTO(
    entryId: 1,
    dataTypeId: 2,
    projectId: 3,
    language: 'en',
    title: 'Title',
    snippet: 'Snippet',
    status: 'draft',
    score: 0.5,
    publishedAt: null
  );

  $array = $dto->toArray();

  // التحقق من أن المفاتيح مطابقة لما يتوقعه الـ API/Frontend
  expect($array)->toBe([
    'entry_id' => 1,
    'data_type_id' => 2,
    'project_id' => 3,
    'language' => 'en',
    'title' => 'Title',
    'snippet' => 'Snippet',
    'status' => 'draft',
    'score' => 0.5,
    'published_at' => null,
  ]);
});
