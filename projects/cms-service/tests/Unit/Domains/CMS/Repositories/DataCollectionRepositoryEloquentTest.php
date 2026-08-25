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
  $project = bindCurrentProject();

  $collection = DataCollection::factory()->create([
    'project_id' => $project->id,
    'slug' => 'test-collection',
  ]);

  $result = $this->repository->getBySlug('test-collection');

  expect($result->id)->toBe($collection->id);
});

test('getBySlug never crosses into another project holding the same slug', function () {
  $mine = bindCurrentProject();
  $theirs = \App\Models\Project::factory()->create();

  // The unique index is (project_id, slug), so the same slug in two projects is
  // valid data. An unscoped lookup returned whichever row was created first.
  $theirCollection = DataCollection::factory()->create([
    'project_id' => $theirs->id,
    'slug' => 'summer-sale',
  ]);

  $myCollection = DataCollection::factory()->create([
    'project_id' => $mine->id,
    'slug' => 'summer-sale',
  ]);

  $result = $this->repository->getBySlug('summer-sale');

  expect($result->id)->toBe($myCollection->id)
    ->and($result->id)->not->toBe($theirCollection->id);
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

// 3. حذف العناصر محصور بالتجميعة المستهدفة
test('it leaves items of another collection untouched', function () {
  $collection1 = DataCollection::factory()->create();
  $collection2 = DataCollection::factory()->create();

  $item = DataCollectionItem::factory()->create(['collection_id' => $collection2->id]);

  // Removing by an item_id that only exists in collection2 is a no-op for
  // collection1 — it used to throw, because the lookup ignored collection_id.
  $this->repository->removeItems($collection1->id, [$item->item_id]);

  expect(DataCollectionItem::find($item->id))->not->toBeNull();
});

test('it removes an entry from one collection while keeping it in another', function () {
  // The exact case the old implementation got wrong: the same entry sitting in
  // two collections made a legitimate removal fail.
  $entry = DataEntry::factory()->create();

  $collection1 = DataCollection::factory()->create();
  $collection2 = DataCollection::factory()->create();

  $inFirst = DataCollectionItem::create([
    'collection_id' => $collection1->id,
    'item_id' => $entry->id,
    'sort_order' => 1,
  ]);

  $inSecond = DataCollectionItem::create([
    'collection_id' => $collection2->id,
    'item_id' => $entry->id,
    'sort_order' => 1,
  ]);

  $this->repository->removeItems($collection2->id, [$entry->id]);

  expect(DataCollectionItem::find($inSecond->id))->toBeNull()
    ->and(DataCollectionItem::find($inFirst->id))->not->toBeNull();
});

test('it renumbers sort_order after a removal', function () {
  $collection = DataCollection::factory()->create();

  $entries = DataEntry::factory()->count(3)->create();

  foreach ($entries as $index => $entry) {
    DataCollectionItem::create([
      'collection_id' => $collection->id,
      'item_id' => $entry->id,
      'sort_order' => $index + 1,
    ]);
  }

  $this->repository->removeItems($collection->id, [$entries[0]->id]);

  $remaining = DataCollectionItem::where('collection_id', $collection->id)
    ->orderBy('sort_order')
    ->pluck('sort_order')
    ->all();

  expect($remaining)->toBe([1, 2]);
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

/*
|--------------------------------------------------------------------------
| is_active enforcement on read paths
|--------------------------------------------------------------------------
*/

test('list hides inactive collections by default and shows them on request', function () {
  $project = \App\Models\Project::factory()->create();

  $active = DataCollection::factory()->create([
    'project_id' => $project->id,
    'is_active' => true,
  ]);

  $inactive = DataCollection::factory()->create([
    'project_id' => $project->id,
    'is_active' => false,
  ]);

  expect($this->repository->list($project->id)->pluck('id')->all())
    ->toBe([$active->id]);

  expect($this->repository->list($project->id, true)->pluck('id')->sort()->values()->all())
    ->toBe(collect([$active->id, $inactive->id])->sort()->values()->all());
});

test('find hides an inactive collection by default', function () {
  $project = \App\Models\Project::factory()->create();

  DataCollection::factory()->create([
    'project_id' => $project->id,
    'slug' => 'hidden',
    'is_active' => false,
  ]);

  expect($this->repository->find($project->id, 'hidden'))->toBeNull()
    ->and($this->repository->find($project->id, 'hidden', true))->not->toBeNull();
});

test('findById hides an inactive collection by default', function () {
  $collection = DataCollection::factory()->create(['is_active' => false]);

  expect($this->repository->findById($collection->id))->toBeNull()
    ->and($this->repository->findById($collection->id, true))->not->toBeNull();
});

/*
|--------------------------------------------------------------------------
| deactivate / reactivate
|--------------------------------------------------------------------------
*/

test('it can deactivate and then reactivate a collection', function () {
  $project = \App\Models\Project::factory()->create();

  $collection = DataCollection::factory()->create([
    'project_id' => $project->id,
    'slug' => 'toggle-me',
    'is_active' => true,
  ]);

  $this->repository->deactivate(new \App\Domains\CMS\DTOs\DataCollection\DeactivateCollectionDTO(
    project_id: $project->id,
    slug: 'toggle-me',
    is_active: false,
  ));

  expect($collection->fresh()->is_active)->toBeFalse();

  // The lookup used to filter on is_active = true, which made this unreachable.
  $this->repository->deactivate(new \App\Domains\CMS\DTOs\DataCollection\DeactivateCollectionDTO(
    project_id: $project->id,
    slug: 'toggle-me',
    is_active: true,
  ));

  expect($collection->fresh()->is_active)->toBeTrue();
});

/*
|--------------------------------------------------------------------------
| replaceItems
|--------------------------------------------------------------------------
*/

test('replaceItems swaps the whole item set and numbers it from one', function () {
  $collection = DataCollection::factory()->create();
  $entries = DataEntry::factory()->count(3)->create();

  DataCollectionItem::create([
    'collection_id' => $collection->id,
    'item_id' => $entries[0]->id,
    'sort_order' => 1,
  ]);

  $this->repository->replaceItems($collection->id, [$entries[1]->id, $entries[2]->id]);

  $rows = DataCollectionItem::where('collection_id', $collection->id)
    ->orderBy('sort_order')
    ->get();

  expect($rows->pluck('item_id')->all())->toBe([$entries[1]->id, $entries[2]->id])
    ->and($rows->pluck('sort_order')->all())->toBe([1, 2]);
});

test('replaceItems with an empty set clears the collection', function () {
  $collection = DataCollection::factory()->create();
  DataCollectionItem::factory()->count(2)->create(['collection_id' => $collection->id]);

  $this->repository->replaceItems($collection->id, []);

  expect(DataCollectionItem::where('collection_id', $collection->id)->count())->toBe(0);
});

test('replaceItems does not touch another collection', function () {
  $mine = DataCollection::factory()->create();
  $other = DataCollection::factory()->create();

  $otherItem = DataCollectionItem::factory()->create(['collection_id' => $other->id]);
  $entry = DataEntry::factory()->create();

  $this->repository->replaceItems($mine->id, [$entry->id]);

  expect(DataCollectionItem::find($otherItem->id))->not->toBeNull();
});

/*
|--------------------------------------------------------------------------
| getCollectionItems
|--------------------------------------------------------------------------
*/

test('getCollectionItems returns items ordered by sort_order with their entry', function () {
  $collection = DataCollection::factory()->create();
  $entries = DataEntry::factory()->count(3)->create();

  // Inserted out of order on purpose: the read used to return insertion order,
  // which made the reorder endpoint invisible.
  DataCollectionItem::create(['collection_id' => $collection->id, 'item_id' => $entries[0]->id, 'sort_order' => 3]);
  DataCollectionItem::create(['collection_id' => $collection->id, 'item_id' => $entries[1]->id, 'sort_order' => 1]);
  DataCollectionItem::create(['collection_id' => $collection->id, 'item_id' => $entries[2]->id, 'sort_order' => 2]);

  $items = $this->repository->getCollectionItems($collection->id);

  expect($items->pluck('item_id')->all())
    ->toBe([$entries[1]->id, $entries[2]->id, $entries[0]->id]);

  $serialised = $items->toArray();

  expect($serialised[0])->toHaveKey('data')
    ->and($serialised[0]['data']['id'])->toBe($entries[1]->id)
    ->and($serialised[0]['data'])->toHaveKey('values')
    ->and($serialised[0])->not->toHaveKey('entry');
});

test('getCollectionItems keeps a null data key when the entry is soft deleted', function () {
  $collection = DataCollection::factory()->create();
  $entry = DataEntry::factory()->create();

  DataCollectionItem::create([
    'collection_id' => $collection->id,
    'item_id' => $entry->id,
    'sort_order' => 1,
  ]);

  // DataEntry soft deletes, so no FK cascade fires: the item row survives while
  // the relation resolves to null. The item must still render, with data: null.
  $entry->delete();

  $items = $this->repository->getCollectionItems($collection->id);

  expect($items)->toHaveCount(1)
    ->and($items->first()->toArray()['data'])->toBeNull();
});
