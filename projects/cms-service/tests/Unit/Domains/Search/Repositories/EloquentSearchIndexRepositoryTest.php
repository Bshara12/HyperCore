<?php

use App\Domains\Search\DTOs\IndexEntryDTO;
use App\Domains\Search\Repositories\Eloquent\EloquentSearchIndexRepository;
use App\Models\SearchIndex;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
  $this->repository = new EloquentSearchIndexRepository();
});

// 1. اختبار الـ Upsert (الحفظ والتحديث)
test('upsert creates new record and updates existing one', function () {
  $dto = IndexEntryDTO::fromArray([
    'entry_id' => 100,
    'data_type_id' => 1,
    'project_id' => 1,
    'language' => 'ar',
    'title' => 'عنوان تجريبي',
    'content' => 'محتوى تجريبي',
    'meta' => ['tags' => ['php', 'test']],
    'status' => 'published',
    'published_at' => now()->toDateTimeString(),
  ]);

  // اختبار الإنشاء
  $this->repository->upsert($dto);
  expect(SearchIndex::count())->toBe(1);

  $record = SearchIndex::first();
  expect($record->title)->toBe('عنوان تجريبي')
    ->and($record->meta)->toBeJson();

  // اختبار التحديث (نفس الـ ID واللغة)
  $updatedDto = IndexEntryDTO::fromArray([
    'entry_id' => 100,
    'data_type_id' => 1,
    'project_id' => 1,
    'language' => 'ar',
    'title' => 'عنوان محدث',
    'content' => 'محتوى محدث',
    'status' => 'draft',
  ]);

  $this->repository->upsert($updatedDto);
  expect(SearchIndex::count())->toBe(1);
  expect(SearchIndex::first()->title)->toBe('عنوان محدث');
});

// 2. اختبار الحذف عبر الـ ID (يحذف كل اللغات)
test('deleteByEntryId removes all entries regardless of language', function () {
  // إضافة سجلين لنفس الـ entry_id بلغات مختلفة
  SearchIndex::factory()->create(['entry_id' => 55, 'language' => 'ar']);
  SearchIndex::factory()->create(['entry_id' => 55, 'language' => 'en']);

  $this->repository->deleteByEntryId(55);

  expect(SearchIndex::where('entry_id', 55)->count())->toBe(0);
});

// 3. اختبار الحذف الدقيق (Entry + Language)
test('deleteByEntryAndLanguage removes only specific entry', function () {
  SearchIndex::factory()->create(['entry_id' => 55, 'language' => 'ar']);
  SearchIndex::factory()->create(['entry_id' => 55, 'language' => 'en']);

  $this->repository->deleteByEntryAndLanguage(55, 'ar');

  expect(SearchIndex::where('entry_id', 55)->where('language', 'ar')->exists())->toBeFalse();
  expect(SearchIndex::where('entry_id', 55)->where('language', 'en')->exists())->toBeTrue();
});

// 4. اختبار التحقق من الوجود
test('existsForEntry returns correct boolean', function () {
  SearchIndex::factory()->create(['entry_id' => 99, 'language' => 'fr']);

  expect($this->repository->existsForEntry(99, 'fr'))->toBeTrue();
  expect($this->repository->existsForEntry(99, 'ar'))->toBeFalse();
});
