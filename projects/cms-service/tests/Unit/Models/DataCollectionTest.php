<?php

use App\Models\DataCollection;
use App\Models\Project;
use App\Models\DataType;
use App\Models\DataCollectionItem;
use App\Models\DataEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('it can create and cast all attributes correctly', function () {
  $project = Project::factory()->create();
  $dataType = DataType::factory()->create();

  // نختبر جميع الخصائص التي تملك Casting
  $collection = DataCollection::create([
    'project_id' => $project->id,
    'data_type_id' => $dataType->id,
    'name' => 'Test Collection',
    'slug' => 'test-col',
    'is_active' => false, // نختبر الـ boolean
    'is_offer' => true,
    'conditions' => ['role' => 'admin'], // نختبر الـ array
    'settings' => ['theme' => 'light'],
  ]);

  // إعادة الجلب للتأكد من أن الـ Casting يعمل عند القراءة
  $collection->refresh();

  expect($collection->is_active)->toBeFalse()
    ->and($collection->is_offer)->toBeTrue()
    ->and($collection->conditions)->toBe(['role' => 'admin'])
    ->and($collection->settings)->toBe(['theme' => 'light']);
});

test('it has relationships', function () {
  $project = Project::factory()->create();
  $dataType = DataType::factory()->create();

  $collection = DataCollection::factory()->create([
    'project_id' => $project->id,
    'data_type_id' => $dataType->id,
  ]);

  // اختبار العلاقة مع المشروع
  expect($collection->project)->toBeInstanceOf(Project::class)
    ->and($collection->project->id)->toBe($project->id);

  // اختبار العلاقة مع نوع البيانات
  expect($collection->dataType)->toBeInstanceOf(DataType::class)
    ->and($collection->dataType->id)->toBe($dataType->id);
});

test('it can retrieve ordered items', function () {
  $collection = DataCollection::factory()->create();

  // إنشاء مدخلات مرتبطة
  $entry1 = DataEntry::factory()->create();
  $entry2 = DataEntry::factory()->create();

  DataCollectionItem::create(['collection_id' => $collection->id, 'item_id' => $entry1->id, 'sort_order' => 2]);
  DataCollectionItem::create(['collection_id' => $collection->id, 'item_id' => $entry2->id, 'sort_order' => 1]);

  // التأكد من أن orderedItems تعيد العناصر مرتبة حسب sort_order
  $items = $collection->orderedItems()->get();

  expect($items)->toHaveCount(2)
    ->and($items->first()->sort_order)->toBe(1)
    ->and($items->last()->sort_order)->toBe(2);
});
