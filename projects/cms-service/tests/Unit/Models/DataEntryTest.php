<?php

use App\Models\DataEntry;
use App\Models\Project;
use App\Models\DataType;
use App\Models\DataEntryValue;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('it can create a data entry with all attributes', function () {
  $project = Project::factory()->create();
  $dataType = DataType::factory()->create();
  $user = User::factory()->create();

  $entry = DataEntry::create([
    'slug' => 'test-entry',
    'data_type_id' => $dataType->id,
    'project_id' => $project->id,
    'status' => 'published',
    'created_by' => $user->id,
  ]);

  expect($entry->slug)->toBe('test-entry')
    ->and($entry->status)->toBe('published')
    ->and($entry->project_id)->toBe($project->id);
});

test('it has the correct relationships', function () {
  $entry = DataEntry::factory()->create();

  // اختبار العلاقات المحددة في الموديل
  expect($entry->project)->toBeInstanceOf(\App\Models\Project::class)
    ->and($entry->dataType)->toBeInstanceOf(\App\Models\DataType::class)
    ->and($entry->values)->toBeInstanceOf(\Illuminate\Database\Eloquent\Collection::class);
});

test('it supports soft deletes', function () {
  $entry = DataEntry::factory()->create();
  $entry->delete();

  // التحقق من أن الموديل تم حذفه منطقياً
  expect(DataEntry::find($entry->id))->toBeNull()
    ->and(DataEntry::withTrashed()->find($entry->id))->not->toBeNull();
});

test('it can access polymorphic ratings', function () {
  $entry = DataEntry::factory()->create();

  // إنشاء تقييم مرتبط بهذا الـ Entry
  $entry->ratings()->create([
    'rating' => 5,
    'user_id' => User::factory()->create()->id
  ]);

  expect($entry->ratings)->toHaveCount(1)
    ->and($entry->ratings->first()->rating)->toBe(5);
});
// 1. اختبار الحالات التي تعيد null للعلاقات (مثلاً عندما لا يكون هناك قيم)
test('it returns empty collection when no values exist', function () {
    $entry = DataEntry::factory()->create();
    expect($entry->values)->toBeEmpty();
});

// 2. اختبار دوال العلاقات الأخرى (التأكد أنها تعيد Query Builder صحيح)
test('it returns query builder for relations', function () {
    $entry = DataEntry::factory()->create();
    
    expect($entry->versions())->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\HasMany::class)
        ->and($entry->relations())->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\HasMany::class);
});