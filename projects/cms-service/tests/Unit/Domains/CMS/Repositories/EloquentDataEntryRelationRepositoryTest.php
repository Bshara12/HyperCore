<?php

use App\Domains\CMS\Repositories\Eloquent\EloquentDataEntryRelationRepository;
use App\Models\DataEntry;
use App\Models\DataType;
use App\Models\DataTypeRelation;
use App\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
  $this->repository = new EloquentDataEntryRelationRepository();
  $this->project = Project::factory()->create();
  $this->dataType = DataType::factory()->create(['project_id' => $this->project->id]);
});

test('it inserts relations correctly', function () {
  $entry = DataEntry::factory()->create(['project_id' => $this->project->id]);
  $relation = DataTypeRelation::factory()->create(['data_type_id' => $this->dataType->id]);
  $relatedEntry = DataEntry::factory()->create(['project_id' => $this->project->id]);

  $relations = [
    ['relation_id' => $relation->id, 'related_entry_ids' => [$relatedEntry->id]]
  ];

  $this->repository->insertForEntry($entry->id, $this->dataType->id, $this->project->id, $relations);

  $this->assertDatabaseHas('data_entry_relations', [
    'data_entry_id' => $entry->id,
    'related_entry_id' => $relatedEntry->id,
    'data_type_relation_id' => $relation->id,
  ]);
});

test('it throws exception if relation does not belong to data type', function () {
  $entry = DataEntry::factory()->create(['project_id' => $this->project->id]);
  $wrongRelation = DataTypeRelation::factory()->create(); // تنتمي لنوع بيانات آخر

  $relations = [
    ['relation_id' => $wrongRelation->id, 'related_entry_ids' => []]
  ];

  expect(fn() => $this->repository->insertForEntry($entry->id, $this->dataType->id, $this->project->id, $relations))
    ->toThrow(\Exception::class, 'Invalid relation: relation does not belong to this data type.');
});

test('it throws exception if related entry not found in project', function () {
  $entry = DataEntry::factory()->create(['project_id' => $this->project->id]);
  $relation = DataTypeRelation::factory()->create(['data_type_id' => $this->dataType->id]);

  // محاولة ربط بـ entry من مشروع آخر
  $otherProject = Project::factory()->create();
  $relatedEntry = DataEntry::factory()->create(['project_id' => $otherProject->id]);

  $relations = [
    ['relation_id' => $relation->id, 'related_entry_ids' => [$relatedEntry->id]]
  ];

  expect(fn() => $this->repository->insertForEntry($entry->id, $this->dataType->id, $this->project->id, $relations))
    ->toThrow(\Exception::class);
});

test('it can perform delete and pluck operations', function () {
  $entry = DataEntry::factory()->create(['project_id' => $this->project->id]);
  $related = DataEntry::factory()->create(['project_id' => $this->project->id]);
  $relation = DataTypeRelation::factory()->create(['data_type_id' => $this->dataType->id, 'relation_type' => 'many_to_one']);

  // إدخال بيانات
  $this->repository->insertForEntry($entry->id, $this->dataType->id, $this->project->id, [
    ['relation_id' => $relation->id, 'related_entry_ids' => [$related->id]]
  ]);

  // اختبار pluck
  $ids = $this->repository->pluckEntryIdsWhereRelatedIs($related->id);
  expect($ids)->toContain($entry->id);

  // اختبار الحذف
  $this->repository->deleteForEntry($entry->id);
  expect($this->repository->getEntriesWhereRelatedIs($related->id))->toBeEmpty();
});

test('it returns empty arrays for pluck methods when inputs are empty', function () {
  expect($this->repository->pluckEntryIdsByRelatedIds([]))->toBe([])
    ->and($this->repository->pluckEntryIdsByRelatedIdsWithin([], []))->toBe([]);
});

test('it deletes relations where related_entry_id matches', function () {
  // 1. إنشاء سجلات حقيقية
  $entry = DataEntry::factory()->create(['project_id' => $this->project->id]);
  $related = DataEntry::factory()->create(['project_id' => $this->project->id]);
  $relation = DataTypeRelation::factory()->create(['data_type_id' => $this->dataType->id]);

  // 2. إنشاء العلاقة باستخدام الـ IDs الحقيقية
  \App\Models\DataEntryRelation::create([
    'data_entry_id' => $entry->id,
    'related_entry_id' => $related->id,
    'data_type_relation_id' => $relation->id
  ]);

  $this->repository->deleteWhereRelatedIs($related->id);

  $this->assertDatabaseMissing('data_entry_relations', ['related_entry_id' => $related->id]);
});

test('it plucks entry ids by related ids correctly', function () {
  $entry = DataEntry::factory()->create(['project_id' => $this->project->id]);
  $related = DataEntry::factory()->create(['project_id' => $this->project->id]);
  $relation = DataTypeRelation::factory()->create(['data_type_id' => $this->dataType->id]);

  \App\Models\DataEntryRelation::create([
    'data_entry_id' => $entry->id,
    'related_entry_id' => $related->id,
    'data_type_relation_id' => $relation->id
  ]);

  $result = $this->repository->pluckEntryIdsByRelatedIds([$related->id]);

  expect($result)->toBe([$entry->id]);
});

test('it plucks entry ids by related ids within other entry ids correctly', function () {
  $entry = DataEntry::factory()->create(['project_id' => $this->project->id]);
  $related = DataEntry::factory()->create(['project_id' => $this->project->id]);
  $relation = DataTypeRelation::factory()->create(['data_type_id' => $this->dataType->id]);

  \App\Models\DataEntryRelation::create([
    'data_entry_id' => $entry->id,
    'related_entry_id' => $related->id,
    'data_type_relation_id' => $relation->id
  ]);

  $result = $this->repository->pluckEntryIdsByRelatedIdsWithin([$related->id], [$entry->id]);

  expect($result)->toBe([$entry->id]);
});
