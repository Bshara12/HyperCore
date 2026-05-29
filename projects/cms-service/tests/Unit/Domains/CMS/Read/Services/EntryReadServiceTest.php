<?php

namespace Tests\Unit\Domains\CMS\Read\Services;

use App\Domains\CMS\Read\Actions\GetEntriesByDataTypeSlugAction;
use App\Domains\CMS\Read\Actions\GetEntriesBySameTypeAction;
use App\Domains\CMS\Read\Actions\GetEntryDetailAction;
use App\Domains\CMS\Read\Actions\GetEntryWithRelationsAction;
use App\Domains\CMS\Read\Actions\GetProjectEntriesAction;
use App\Domains\CMS\Read\Actions\GetProjectEntriesTreeAction;
use App\Domains\CMS\Read\DTOs\GetEntryDetailDTO;
use App\Domains\CMS\Read\Services\EntryReadService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;

beforeEach(function () {
  $this->getEntryDetailAction = Mockery::mock(GetEntryDetailAction::class);
  $this->getEntryWithRelationsAction = Mockery::mock(GetEntryWithRelationsAction::class);
  $this->getEntriesBySameTypeAction = Mockery::mock(GetEntriesBySameTypeAction::class);
  $this->getProjectEntriesAction = Mockery::mock(GetProjectEntriesAction::class);
  $this->getProjectEntriesTreeAction = Mockery::mock(GetProjectEntriesTreeAction::class);
  $this->getEntriesByDataTypeSlugAction = Mockery::mock(GetEntriesByDataTypeSlugAction::class);

  $this->service = new EntryReadService(
    $this->getEntryDetailAction,
    $this->getEntryWithRelationsAction,
    $this->getEntriesBySameTypeAction,
    $this->getProjectEntriesAction,
    $this->getProjectEntriesTreeAction,
    $this->getEntriesByDataTypeSlugAction
  );
});

afterEach(function () {
  Mockery::close();
});

uses(RefreshDatabase::class);

test('it calls getDetail with correct DTO', function () {
  // إنشاء DTO الخاص بالمدخلات
  $inputDto = new GetEntryDetailDTO(1, 'en', 123);

  // إنشاء Mock للكائن الذي يجب أن يعيده الأكشن (EntryDetailDTO)
  // لاحظ تأكد من استخدام المسار الكامل للكلاس المذكور في رسالة الخطأ
  $expectedResultDto = Mockery::mock(\App\Domains\CMS\Read\DTOs\EntryDetailDTO::class);

  $this->getEntryDetailAction->shouldReceive('execute')
    ->once()
    ->with($inputDto)
    ->andReturn($expectedResultDto);

  $result = $this->service->getDetail($inputDto);

  // التأكد من أن النتيجة هي نفس الكائن الذي أرجعه الموك
  expect($result)->toBe($expectedResultDto);
});

test('it calls getWithRelations with correct parameters', function () {
  $this->getEntryWithRelationsAction->shouldReceive('execute')->once()->with(1, 'en')->andReturn(['relations' => []]);

  $result = $this->service->getWithRelations(1, 'en');
  expect($result)->toBe(['relations' => []]);
});

test('it calls getSameType with basic parameters', function () {
  $this->getEntriesBySameTypeAction->shouldReceive('execute')->once()->with(1, 'en', 1, 20, false)->andReturn(['items' => []]);

  $result = $this->service->getSameType(1, 'en');
  expect($result)->toBe(['items' => []]);
});

test('it calls getSameTypeFiltered with all search parameters', function () {
  $this->getEntriesBySameTypeAction->shouldReceive('execute')
    ->once()
    ->with(1, 'en', 1, 20, false, '2026-01-01', '2026-05-28', 5, 'search-term')
    ->andReturn(['items' => []]);

  $result = $this->service->getSameTypeFiltered(1, 'en', '2026-01-01', '2026-05-28', 5, 'search-term', false, 1, 20);
  expect($result)->toBe(['items' => []]);
});

test('it calls getProjectEntries with correct filters', function () {
  $filters = ['status' => 'published'];
  $this->getProjectEntriesAction->shouldReceive('execute')->once()->with(1, $filters)->andReturn(['entries' => []]);

  $result = $this->service->getProjectEntries(1, $filters);
  expect($result)->toBe(['entries' => []]);
});

test('it calls getProjectEntriesTree with correct filters', function () {
  $filters = ['parent_id' => null];
  $this->getProjectEntriesTreeAction->shouldReceive('execute')->once()->with(1, $filters)->andReturn(['tree' => []]);

  $result = $this->service->getProjectEntriesTree(1, $filters);
  expect($result)->toBe(['tree' => []]);
});

test('it calls getEntriesByDataTypeSlug with correct parameters', function () {
  $filters = [];
  $this->getEntriesByDataTypeSlugAction->shouldReceive('execute')->once()->with(1, 'blog', $filters)->andReturn(['entries' => []]);

  $result = $this->service->getEntriesByDataTypeSlug(1, 'blog', $filters);
  expect($result)->toBe(['entries' => []]);
});

test('it executes showMany by querying DataEntry model', function () {
  // 1. إنشاء سجلات حقيقية في قاعدة بيانات الاختبار باستخدام الـ Factory
  $entries = \App\Models\DataEntry::factory()->count(2)->create();
  $ids = $entries->pluck('id')->toArray();

  // 2. استدعاء الدالة المراد اختبارها
  $result = $this->service->showMany($ids);

  // 3. التأكد من جلب السجلين بنجاح
  expect($result)->toHaveCount(2);
});
