<?php

use App\Domains\CMS\Repositories\Eloquent\DataCollectionRepositoryEloquent;
use App\Models\DataCollection;
use App\Models\DataCollectionItem;
use App\Models\DataEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
  $this->repository = new DataCollectionRepositoryEloquent();
});

test('it can get collection by slug', function () {
  $collection = DataCollection::factory()->create(['slug' => 'test-collection']);

  $result = $this->repository->getBySlug('test-collection');

  expect($result->id)->toBe($collection->id);
});

test('it can reorder items correctly./vendor/bin/pest tests/Unit/Domains/CMS/Repositories/DataCollectionRepositoryEloquentTest.php --coverage', function () {
  $collection = DataCollection::factory()->create();

  // إنشاء 3 عناصر
  $item1 = DataCollectionItem::factory()->create(['collection_id' => $collection->id, 'sort_order' => 1]);
  $item2 = DataCollectionItem::factory()->create(['collection_id' => $collection->id, 'sort_order' => 2]);
  $item3 = DataCollectionItem::factory()->create(['collection_id' => $collection->id, 'sort_order' => 3]);

  // إعادة ترتيب: نجعل item2 في المركز الأول
  $itemsToReorder = [
    ['item_id' => $item2->item_id, 'sort_order' => 1],
    ['item_id' => $item1->item_id, 'sort_order' => 2],
    ['item_id' => $item3->item_id, 'sort_order' => 3],
  ];

  $this->repository->reOrderItems($collection->id, $itemsToReorder);

  // التحقق من الترتيب الجديد
  $sortedItems = DataCollectionItem::where('collection_id', $collection->id)
    ->orderBy('sort_order')
    ->get();

  expect($sortedItems[0]->item_id)->toBe($item2->item_id)
    ->and($sortedItems[1]->item_id)->toBe($item1->item_id)
    ->and($sortedItems[2]->item_id)->toBe($item3->item_id);
});

test('it deactivates collection correctly', function () {
  // محاكاة الكائن الذي يعتمد عليه الـ DTO
  $project = \App\Models\Project::factory()->create(['public_id' => 'proj-123']);
  app()->instance('currentProject', $project);

  // Mock للـ Repository المعتمد عليه داخل الـ DTO
  $projectRepo = Mockery::mock(\App\Domains\CMS\Repositories\Interface\ProjectRepositoryInterface::class);
  $projectRepo->shouldReceive('findByKey')->with('proj-123')->andReturn($project);
  app()->instance(\App\Domains\CMS\Repositories\Interface\ProjectRepositoryInterface::class, $projectRepo);

  $collection = DataCollection::factory()->create([
    'slug' => 'my-col',
    'project_id' => $project->id,
    'is_active' => true
  ]);

  // تحضير الـ DTO
  $request = \App\Domains\CMS\Requests\DeactivateCollectionRequest::create('/', 'POST', ['is_active' => false]);
  $dto = \App\Domains\CMS\DTOs\DataCollection\DeactivateCollectionDTO::fromRequest('my-col', $request);

  $this->repository->deactivate($dto);

  expect($collection->fresh()->is_active)->toBeFalse();
});

test('it gets collection items with entries', function () {
  $collection = DataCollection::factory()->create();
  $entry = DataEntry::factory()->create();

  DataCollectionItem::create([
    'collection_id' => $collection->id,
    'item_id' => $entry->id,
    'sort_order' => 1
  ]);

  $items = $this->repository->getCollectionItems($collection->id);

  expect($items)->toHaveCount(1)
    ->and($items->first()->data->id)->toBe($entry->id);
});

// 1. اختبار العمليات الأساسية (CRUD)
test('it can create, update, delete, and list collections', function () {
  // 1. إنشاء البيانات المعتمدة عليها (الآباء) أولاً
  $project = \App\Models\Project::factory()->create();
  $dataType = \App\Models\DataType::factory()->create();

  // 2. استخدام الـ IDs الحقيقية الناتجة عن الـ Factory
  $dto = Mockery::mock(\App\Domains\CMS\DTOs\DataCollection\UpdateDataCollectionDTO::class);
  $dto->shouldReceive('CollectionToArray')->andReturn([
    'name' => 'Test',
    'slug' => 'test',
    'project_id' => $project->id, // استخدام الـ ID الحقيقي
    'data_type_id' => $dataType->id // استخدام الـ ID الحقيقي
  ]);

  // 3. باقي الاختبار كما هو
  $created = $this->repository->create($dto);
  expect($created->name)->toBe('Test');

  // اختبار update
  $updateDto = Mockery::mock(\App\Domains\CMS\DTOs\DataCollection\UpdateDataCollectionDTO::class);
  $updateDto->collection_id = $created->id;
  $updateDto->shouldReceive('toArray')->andReturn(['name' => 'Updated Name']);

  $this->repository->update($updateDto);
  expect($created->fresh()->name)->toBe('Updated Name');

  // اختبار findById و list
  expect($this->repository->findById($created->id))->not->toBeNull()
    ->and($this->repository->list($project->id))->toHaveCount(1); // استخدام project_id الحقيقي

  // اختبار delete
  $this->repository->delete($created->id);
  expect(DataCollection::find($created->id))->toBeNull();
});

// 2. اختبار منطق insertItems ومنع التكرار
test('it inserts items and prevents duplicates', function () {
  $collection = DataCollection::factory()->create();
  $entry = DataEntry::factory()->create(); // إنشاء سجل حقيقي

  $this->repository->insertItems($collection->id, [$entry->id, $entry->id]); // تمرير ID حقيقي

  $items = DataCollectionItem::where('collection_id', $collection->id)->get();
  expect($items)->toHaveCount(1);
});

// 3. اختبار حذف العناصر مع الـ DomainException
test('it throws exception when removing items from different collection', function () {
  $collection1 = DataCollection::factory()->create();
  $collection2 = DataCollection::factory()->create();

  $item = DataCollectionItem::factory()->create(['collection_id' => $collection2->id]);

  // محاولة حذف عنصر ينتمي لـ collection2 باستخدام collection1
  expect(fn() => $this->repository->removeItems($collection1->id, [$item->item_id]))
    ->toThrow(DomainException::class, "You can't remove items from different collection.");
});

// 4. اختبار getEntries (تحتاج لتجهيز العلاقات)
test('it gets formatted entries with prices', function () {
  $collection = DataCollection::factory()->create();
  // يجب إنشاء DataEntry وعلاقاتها هنا لتغطية هذا الجزء
  // (بافتراض أن لديك Factory للـ DataEntry والـ Values)
  $entry = DataEntry::factory()->create();

  DataCollectionItem::create([
    'collection_id' => $collection->id,
    'item_id' => $entry->id,
    'sort_order' => 1
  ]);

  $data = $this->repository->getEntries($collection->id);

  // تأكد من التحقق من هيكل المصفوفة المرجعة
  expect($data)->toBeArray();
});

// اختبار: createDataCollectionItem
test('it can create collection item', function () {
  $collection = DataCollection::factory()->create();
  // إنشاء سجل حقيقي في جدول entries أولاً
  $entry = DataEntry::factory()->create();

  $data = [
    'collection_id' => $collection->id,
    'item_id' => $entry->id, // استخدام الـ ID الحقيقي
    'sort_order' => 1
  ];

  $this->repository->createDataCollectionItem($data);

  $this->assertDatabaseHas('data_collection_items', $data);
});

// اختبار: deleteItems
test('it can delete all items for a specific collection', function () {
  $collection = DataCollection::factory()->create();
  DataCollectionItem::factory()->count(3)->create(['collection_id' => $collection->id]);

  $this->repository->deleteItems($collection->id);

  $remaining = DataCollectionItem::where('collection_id', $collection->id)->count();
  expect($remaining)->toBe(0);
});

// اختبار: find (بواسطة projectId و slug)
test('it can find collection by project and slug', function () {
  $project = \App\Models\Project::factory()->create();
  $collection = DataCollection::factory()->create([
    'project_id' => $project->id,
    'slug' => 'unique-slug'
  ]);

  $result = $this->repository->find($project->id, 'unique-slug');

  expect($result)->not->toBeNull()
    ->and($result->id)->toBe($collection->id);
});

// اختبار: pluckCollectionEntryIds
test('it can pluck collection entry ids sorted by order', function () {
  $collection = DataCollection::factory()->create();

  // إنشاء سجلات حقيقية
  $entry1 = DataEntry::factory()->create();
  $entry2 = DataEntry::factory()->create();

  // استخدام الـ IDs الحقيقية
  DataCollectionItem::factory()->create(['collection_id' => $collection->id, 'item_id' => $entry1->id, 'sort_order' => 2]);
  DataCollectionItem::factory()->create(['collection_id' => $collection->id, 'item_id' => $entry2->id, 'sort_order' => 1]);

  $ids = $this->repository->pluckCollectionEntryIds($collection->id);

  // الترتيب الصحيح: [الـ ID الخاص بـ entry2 ثم entry1]
  expect($ids)->toBe([$entry2->id, $entry1->id]);
});

// 1. اختبار حالة الـ "Continue" (عندما لا يوجد العنصر في قاعدة البيانات)
test('it continues when attempting to remove non-existent item', function () {
  $collection = DataCollection::factory()->create();

  // تمرير ID غير موجود (مثلاً 9999)
  $this->repository->removeItems($collection->id, [9999]);

  // التوقعات: لا يجب أن يحدث خطأ، ويجب أن يمر الاختبار بنجاح
  expect(true)->toBeTrue();
});

// 2. اختبار منطق الحذف وإعادة الترتيب (Reordering)
test('it deletes item and successfully reorders remaining items', function () {
  $collection = DataCollection::factory()->create();

  // إنشاء 3 عناصر مرتبة 1، 2، 3
  $e1 = DataEntry::factory()->create();
  $e2 = DataEntry::factory()->create();
  $e3 = DataEntry::factory()->create();

  DataCollectionItem::factory()->create(['collection_id' => $collection->id, 'item_id' => $e1->id, 'sort_order' => 1]);
  $itemToDelete = DataCollectionItem::factory()->create(['collection_id' => $collection->id, 'item_id' => $e2->id, 'sort_order' => 2]);
  DataCollectionItem::factory()->create(['collection_id' => $collection->id, 'item_id' => $e3->id, 'sort_order' => 3]);

  // تنفيذ الحذف للعنصر الأوسط (sort_order = 2)
  $this->repository->removeItems($collection->id, [$itemToDelete->item_id]);

  // التحقق 1: التأكد من حذف العنصر فعلياً
  expect(DataCollectionItem::where('item_id', $itemToDelete->item_id)->exists())->toBeFalse();

  // التحقق 2: التأكد من إعادة ترتيب العناصر المتبقية
  $remaining = DataCollectionItem::where('collection_id', $collection->id)
    ->orderBy('sort_order')
    ->get();

  expect($remaining)->toHaveCount(2)
    ->and($remaining[0]->sort_order)->toBe(1) // e1 بقي في مكانه
    ->and($remaining[1]->sort_order)->toBe(2) // e3 أصبح مكانه 2 بدلاً من 3
    ->and($remaining[1]->item_id)->toBe($e3->id);
});

test('it handles orphan items in getEntries by skipping them', function () {
  $collection = DataCollection::factory()->create();

  // 1. إنشاء عنصر حقيقي
  $entry = DataEntry::factory()->create();
  DataCollectionItem::create([
    'collection_id' => $collection->id,
    'item_id' => $entry->id,
    'sort_order' => 1
  ]);

  // 2. حذف الـ Entry يدوياً لجعله "يتيماً"
  $entry->delete();

  // 3. الآن دالة getEntries ستواجه عنصراً في الجدول لا يملك Entry مقابلاً
  $data = $this->repository->getEntries($collection->id);

  // يجب أن تتخطى الكود العنصر ولا يعود أي بيانات
  expect($data)->toBeEmpty();
});
test('it does nothing when deactivating a non-existent collection', function () {
  // تصحيح الترتيب والأنواع
  $dto = new \App\Domains\CMS\DTOs\DataCollection\DeactivateCollectionDTO(
    999,                    // project_id (int)
    'non-existent-slug',    // slug (string)
    false                   // is_active (bool)
  );

  // لن يحدث استثناء، الاختبار سيمر بنجاح
  $this->repository->deactivate($dto);

  expect(true)->toBeTrue();
});
