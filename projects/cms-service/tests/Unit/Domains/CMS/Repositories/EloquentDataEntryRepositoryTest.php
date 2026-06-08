<?php

use App\Domains\CMS\Repositories\Eloquent\EloquentDataEntryRepository;
use App\Models\DataEntry;
use App\Models\DataEntryValue;
use App\Models\Project;
use App\Models\DataType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Database\Eloquent\ModelNotFoundException;

uses(RefreshDatabase::class);

beforeEach(function () {
  $this->repository = new EloquentDataEntryRepository();
  $this->project = Project::factory()->create();
  $this->dataType = DataType::factory()->create(['project_id' => $this->project->id]);
});

test('it can create and find data entry', function () {
  // إضافة slug هنا
  $entry = $this->repository->create([
    'project_id' => $this->project->id,
    'data_type_id' => $this->dataType->id,
    'slug' => 'test-entry'
  ]);

  expect($this->repository->find($entry->id)->id)->toBe($entry->id);
});

test('it throws exception when finding entry for wrong project', function () {
  $entry = DataEntry::factory()->create(['project_id' => $this->project->id]);

  expect(fn() => $this->repository->findForProjectOrFail($entry->id, 999))
    ->toThrow(ModelNotFoundException::class);
});

test('it updates status and handles scheduling', function () {
  $entry = DataEntry::factory()->create([
    'project_id' => $this->project->id,
    'data_type_id' => $this->dataType->id,
    'slug' => 'test-slug'
  ]);

  // اختبار تحديث الحالة
  $this->repository->updateStatus($entry->id, 'published');
  expect($entry->fresh()->status)->toBe('published');

  // اختبار الجدولة
  $this->repository->schedule($entry->id, '2026-06-01 10:00:00');

  // بدلاً من سحب الموديل والتعامل مع الكائنات، نفحص قاعدة البيانات مباشرة
  // 💡 ملاحظة: قم بتغيير 'publish_at' إلى 'published_at' إذا كان هذا هو الاسم في الـ Repository
  $this->assertDatabaseHas('data_entries', [
    'id' => $entry->id,
    'status' => 'scheduled',
    'published_at' => '2026-06-01 10:00:00'
  ]);
});
test('it plucks ids by project type and values', function () {
  $entry = DataEntry::factory()->create([
    'project_id' => $this->project->id,
    'data_type_id' => $this->dataType->id,
    'slug' => 'val-entry' // إضافة slug
  ]);

  DataEntryValue::factory()->create(['data_entry_id' => $entry->id, 'value' => 'active']);

  $ids = $this->repository->pluckIdsByProjectTypeAndValues($this->project->id, $this->dataType->id, ['active']);

  expect($ids)->toContain($entry->id);
});

test('it handles empty values in pluck methods', function () {
  expect($this->repository->pluckIdsByProjectTypeAndValues($this->project->id, $this->dataType->id, []))->toBeEmpty();
});

test('it plucks ids for project type excluding specific ids', function () {
  $entry1 = DataEntry::factory()->create(['project_id' => $this->project->id, 'data_type_id' => $this->dataType->id]);
  $entry2 = DataEntry::factory()->create(['project_id' => $this->project->id, 'data_type_id' => $this->dataType->id]);

  $ids = $this->repository->pluckIdsForProjectTypeExcluding($this->project->id, $this->dataType->id, [$entry1->id]);

  expect($ids)->not->toContain($entry1->id)
    ->and($ids)->toContain($entry2->id);
});

test('it manages rating stats correctly', function () {
  $entry = DataEntry::factory()->create(['ratings_count' => 0, 'ratings_avg' => 0]);

  $this->repository->updateRatingStats($entry->id, ['ratings_count' => 5, 'ratings_avg' => 4.5]);

  $stats = $this->repository->getRatingStats($entry->id);
  expect($stats['ratings_count'])->toBe(5)
    ->and($stats['ratings_avg'])->toBe(4.5);

  // اختبار حالة عدم وجود السجل في getRatingStats
  expect($this->repository->getRatingStats(9999))->toBe(['ratings_count' => 0, 'ratings_avg' => 0]);
});

test('it can find data entry or fail', function () {
  // إنشاء سجل
  $entry = DataEntry::factory()->create();

  // اختبار العثور على السجل
  $found = $this->repository->findOrFail($entry->id);
  expect($found->id)->toBe($entry->id);

  // اختبار رمي استثناء عند عدم العثور على السجل
  $this->repository->findOrFail(9999);
})->throws(\Illuminate\Database\Eloquent\ModelNotFoundException::class);

test('it can touch updated by', function () {
  // 1. إنشاء مستخدم أولاً ليتمكن الـ Foreign Key من التحقق منه
  $user = \App\Models\User::factory()->create();

  // 2. إنشاء السجل
  $entry = DataEntry::factory()->create(['updated_by' => null]);

  // 3. تمرير الـ id الخاص بالمستخدم الذي أنشأناه
  $this->repository->touchUpdatedBy($entry->id, $user->id);

  $this->assertDatabaseHas('data_entries', [
    'id' => $entry->id,
    'updated_by' => $user->id,
  ]);
});

test('it can pluck ids for project', function () {
  $project = \App\Models\Project::factory()->create();

  // إنشاء سجلين لنفس المشروع
  $entry1 = DataEntry::factory()->create(['project_id' => $project->id]);
  $entry2 = DataEntry::factory()->create(['project_id' => $project->id]);

  // إنشاء سجل لمشروع آخر (لا يجب أن يظهر)
  DataEntry::factory()->create();

  $ids = $this->repository->pluckIdsForProject($project->id);

  expect($ids)->toHaveCount(2)
    ->and($ids)->toContain($entry1->id, $entry2->id);
});

test('it can find many data entries by their ids', function () {
  // 1. إنشاء سجلات تجريبية في قاعدة البيانات
  $entry1 = DataEntry::factory()->create(['project_id' => $this->project->id, 'data_type_id' => $this->dataType->id]);
  $entry2 = DataEntry::factory()->create(['project_id' => $this->project->id, 'data_type_id' => $this->dataType->id]);
  $entry3 = DataEntry::factory()->create(['project_id' => $this->project->id, 'data_type_id' => $this->dataType->id]);

  // 2. استدعاء الدالة للبحث عن السجل الأول والثاني فقط
  $results = $this->repository->findManyByIds([$entry1->id, $entry2->id]);

  // 3. التأكد من جلب سجلين فقط وأن السجل الثالث غير موجود بالنتائج
  expect($results)->toHaveCount(2);

  // تحويل النتائج لمصفوفة معرفات للتأكد من المحتوى بدقة
  $extractedIds = collect($results)->pluck('id')->toArray();
  expect($extractedIds)->toContain($entry1->id, $entry2->id)
    ->and($extractedIds)->not->toContain($entry3->id);
});
