<?php

use App\Domains\CMS\Repositories\Eloquent\EloquentDataEntryValueRepository;
use App\Models\DataCollection;
use App\Models\DataCollectionItem;
use App\Models\DataEntry;
use App\Models\DataType;
use App\Models\DataTypeField;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
  $this->repository = new EloquentDataEntryValueRepository();
  $this->dataType = DataType::factory()->create();
  $this->field = DataTypeField::factory()->create([
    'data_type_id' => $this->dataType->id,
    'name' => 'title'
  ]);
});

test('it can bulk insert values', function () {
  $entry = DataEntry::factory()->create([
    'data_type_id' => $this->dataType->id,
    'created_by' => null,
    'updated_by' => null,
  ]);

  $values = ['title' => ['en' => 'Hello', 'ar' => 'مرحباً']];
  $this->repository->bulkInsert($entry->id, $this->dataType->id, $values);

  $this->assertDatabaseHas('data_entry_values', [
    'data_entry_id' => $entry->id,
    'value' => 'Hello'
  ]);
});

test('it can replace existing values for a field', function () {
  $entry = DataEntry::factory()->create(['data_type_id' => $this->dataType->id]);

  // 1. إدخال قيمة قديمة في قاعدة البيانات
  DB::table('data_entry_values')->insert([
    'data_entry_id' => $entry->id,
    'data_type_field_id' => $this->field->id,
    'language' => 'en',
    'value' => 'Old English Value',
    'created_at' => now(),
    'updated_at' => now(),
  ]);

  // 2. استدعاء التابع لاستبدال القيمة
  $this->repository->replacePartial($entry->id, $this->dataType->id, [
    'title' => ['en' => 'New English Value']
  ]);

  // 3. التحقق من أن القيمة الجديدة موجودة والقديمة حُذفت
  $this->assertDatabaseHas('data_entry_values', [
    'data_entry_id' => $entry->id,
    'language' => 'en',
    'value' => 'New English Value'
  ]);

  $this->assertDatabaseMissing('data_entry_values', [
    'value' => 'Old English Value'
  ]);
});

test('it handles non-translatable fields correctly', function () {
  $entry = DataEntry::factory()->create(['data_type_id' => $this->dataType->id]);

  // إدخال قيم بلغات متعددة لحقل سيتم تحويله لغير مترجم
  DB::table('data_entry_values')->insert([
    ['data_entry_id' => $entry->id, 'data_type_field_id' => $this->field->id, 'language' => 'en', 'value' => 'A', 'created_at' => now(), 'updated_at' => now()],
    ['data_entry_id' => $entry->id, 'data_type_field_id' => $this->field->id, 'language' => 'ar', 'value' => 'B', 'created_at' => now(), 'updated_at' => now()],
  ]);

  // استبدال بقيمة غير مترجمة (null هو المفتاح الافتراضي في normalizeFieldValue)
  $this->repository->replacePartial($entry->id, $this->dataType->id, [
    'title' => 'Single Value'
  ]);

  // يجب حذف كل اللغات السابقة والاحتفاظ بالقيمة الجديدة فقط
  $this->assertDatabaseCount('data_entry_values', 1);
  $this->assertDatabaseHas('data_entry_values', [
    'data_entry_id' => $entry->id,
    'language' => null,
    'value' => 'Single Value'
  ]);
});

test('it can get all values for a specific entry', function () {
  $entry = DataEntry::factory()->create();

  // إدخال بيانات تجريبية
  DB::table('data_entry_values')->insert([
    ['data_entry_id' => $entry->id, 'data_type_field_id' => $this->field->id, 'value' => 'Val 1', 'created_at' => now(), 'updated_at' => now()],
    ['data_entry_id' => $entry->id, 'data_type_field_id' => $this->field->id, 'value' => 'Val 2', 'created_at' => now(), 'updated_at' => now()],
  ]);

  $values = $this->repository->getForEntry($entry->id);

  expect($values)->toHaveCount(2)
    ->and($values[0])->toHaveKey('value', 'Val 1');
});

test('it can delete all values for an entry', function () {
  $entry = DataEntry::factory()->create();

  DB::table('data_entry_values')->insert([
    'data_entry_id' => $entry->id,
    'data_type_field_id' => $this->field->id,
    'value' => 'To be deleted',
    'created_at' => now(),
    'updated_at' => now(),
  ]);

  $this->repository->deleteForEntry($entry->id);

  $this->assertDatabaseMissing('data_entry_values', ['data_entry_id' => $entry->id]);
});

test('it can bulk insert from snapshot', function () {
  $entry = DataEntry::factory()->create();
  $snapshot = [
    ['data_type_field_id' => $this->field->id, 'language' => 'en', 'value' => 'Snapshot Value']
  ];

  $this->repository->bulkInsertFromSnapshot($entry->id, $snapshot);

  $this->assertDatabaseHas('data_entry_values', [
    'data_entry_id' => $entry->id,
    'value' => 'Snapshot Value'
  ]);
});

test('it can pluck entry ids by field comparison', function () {
  $entry = DataEntry::factory()->create();

  DB::table('data_entry_values')->insert([
    'data_entry_id' => $entry->id,
    'data_type_field_id' => $this->field->id,
    'value' => '500',
    'created_at' => now(),
    'updated_at' => now(),
  ]);

  // البحث عن قيم أكبر من 400
  $ids = $this->repository->pluckEntryIdsByFieldComparison('title', '>', '400');

  expect($ids)->toContain($entry->id);

  // البحث عن قيم أقل من 100 (لا يجب أن يجد شيئاً)
  $wrongIds = $this->repository->pluckEntryIdsByFieldComparison('title', '<', '100');
  expect($wrongIds)->toBeEmpty();
});

test('it can pluck entry ids by field like pattern', function () {
  $entry = DataEntry::factory()->create();
  DB::table('data_entry_values')->insert([
    'data_entry_id' => $entry->id,
    'data_type_field_id' => $this->field->id,
    'value' => 'Laravel Framework',
    'created_at' => now(),
    'updated_at' => now(),
  ]);

  $ids = $this->repository->pluckEntryIdsByFieldLike('title', 'Laravel%');

  expect($ids)->toContain($entry->id);
});

test('it can pluck entry ids by field in values', function () {
  $entry = DataEntry::factory()->create();
  DB::table('data_entry_values')->insert([
    'data_entry_id' => $entry->id,
    'data_type_field_id' => $this->field->id,
    'value' => 'Option A',
    'created_at' => now(),
    'updated_at' => now(),
  ]);

  $ids = $this->repository->pluckEntryIdsByFieldIn('title', ['Option A', 'Option B']);

  expect($ids)->toContain($entry->id);
});

test('it can pluck collection ids by project and type', function () {
  $collection = DataCollection::factory()->create([
    'project_id' => 1,
    'data_type_id' => $this->dataType->id,
    'slug' => 'test-collection'
  ]);

  $ids = $this->repository->pluckEntryIdsByFieldInCollection(1, $this->dataType->id, ['test-collection']);

  expect($ids)->toContain($collection->id);
});

test('it can return unique entry ids from collection items', function () {
  // 1. إنشاء الـ Collection
  $collection = DataCollection::factory()->create();

  // 2. إنشاء الـ Entry (الذي يمثل الـ item_id في العلاقة)
  // لاحظ أننا نستخدم id محدد أو نأخذ الـ id من السجل المنشأ
  $entry = DataEntry::factory()->create(['id' => 10]);

  // 3. إدخال العلاقة
  DataCollectionItem::create([
    'collection_id' => $collection->id,
    'item_id' => $entry->id,
    'sort_order' => 0
  ]);

  $itemIds = $this->repository->returnEntryIdsFromCollectionItems([$collection->id]);

  expect($itemIds)->toContain(10);
});

test('it can pluck entry ids by field between range', function () {
  $entry = DataEntry::factory()->create();
  DB::table('data_entry_values')->insert([
    'data_entry_id' => $entry->id,
    'data_type_field_id' => $this->field->id,
    'value' => '20',
    'created_at' => now(),
    'updated_at' => now(),
  ]);

  $ids = $this->repository->pluckEntryIdsByFieldBetween('title', [10, 30]);

  expect($ids)->toContain($entry->id);

  // اختبار الحالة الخاطئة (أقل من قيمتين)
  $empty = $this->repository->pluckEntryIdsByFieldBetween('title', [10]);
  expect($empty)->toBeEmpty();
});

test('it returns empty array when values are empty in pluckEntryIdsByFieldIn', function () {
  // نمرر مصفوفة فارغة
  $ids = $this->repository->pluckEntryIdsByFieldIn('title', []);

  // نتحقق من أن النتيجة هي مصفوفة فارغة
  expect($ids)->toBeArray()
    ->toBeEmpty();
});

test('it can pluck numeric field values by entry ids', function () {
  $entry = DataEntry::factory()->create();
  DB::table('data_entry_values')->insert([
    'data_entry_id' => $entry->id,
    'data_type_field_id' => $this->field->id,
    'value' => '150.5',
    'created_at' => now(),
    'updated_at' => now(),
  ]);

  $result = $this->repository->pluckNumericFieldValuesByEntryIds('title', [$entry->id]);

  expect($result)->toBe([$entry->id => 150.5]);

  // اختبار المسار الفارغ
  expect($this->repository->pluckNumericFieldValuesByEntryIds('title', []))->toBeEmpty();
});

test('it can pluck entry ids by comparison within a set', function () {
  $entry = DataEntry::factory()->create();
  DB::table('data_entry_values')->insert([
    'data_entry_id' => $entry->id,
    'data_type_field_id' => $this->field->id,
    'value' => '200',
    'created_at' => now(),
    'updated_at' => now(),
  ]);

  $ids = $this->repository->pluckEntryIdsByFieldComparisonWithin('title', '>', '100', [$entry->id]);
  expect($ids)->toContain($entry->id);

  // اختبار المسار الفارغ
  expect($this->repository->pluckEntryIdsByFieldComparisonWithin('title', '>', '100', []))->toBeEmpty();
});

test('it can pluck entry ids by like within a set', function () {
  $entry = DataEntry::factory()->create();
  DB::table('data_entry_values')->insert([
    'data_entry_id' => $entry->id,
    'data_type_field_id' => $this->field->id,
    'value' => 'Test Item',
    'created_at' => now(),
    'updated_at' => now(),
  ]);

  $ids = $this->repository->pluckEntryIdsByFieldLikeWithin('title', 'Test%', [$entry->id]);
  expect($ids)->toContain($entry->id);

  // اختبار المسار الفارغ
  expect($this->repository->pluckEntryIdsByFieldLikeWithin('title', 'Test%', []))->toBeEmpty();
});

test('it can pluck entry ids by field in within a set', function () {
  $entry = DataEntry::factory()->create();
  DB::table('data_entry_values')->insert([
    'data_entry_id' => $entry->id,
    'data_type_field_id' => $this->field->id,
    'value' => 'A',
    'created_at' => now(),
    'updated_at' => now(),
  ]);

  $ids = $this->repository->pluckEntryIdsByFieldInWithin('title', ['A', 'B'], [$entry->id]);
  expect($ids)->toContain($entry->id);

  // اختبار الحالات الفارغة
  expect($this->repository->pluckEntryIdsByFieldInWithin('title', [], [$entry->id]))->toBeEmpty()
    ->and($this->repository->pluckEntryIdsByFieldInWithin('title', ['A'], []))->toBeEmpty();
});

test('it can pluck entry ids by field between within a set', function () {
  $entry = DataEntry::factory()->create();
  DB::table('data_entry_values')->insert([
    'data_entry_id' => $entry->id,
    'data_type_field_id' => $this->field->id,
    'value' => '50',
    'created_at' => now(),
    'updated_at' => now(),
  ]);

  $ids = $this->repository->pluckEntryIdsByFieldBetweenWithin('title', [40, 60], [$entry->id]);
  expect($ids)->toContain($entry->id);

  // اختبار الحالات الفارغة/الخاطئة
  expect($this->repository->pluckEntryIdsByFieldBetweenWithin('title', [40], [$entry->id]))->toBeEmpty()
    ->and($this->repository->pluckEntryIdsByFieldBetweenWithin('title', [40, 60], []))->toBeEmpty();
});

test('it throws an exception if field does not exist in data type', function () {
  $entry = DataEntry::factory()->create(['data_type_id' => $this->dataType->id]);

  // محاولة إدخال بيانات لحقل وهمي اسمه 'non_existent_field'
  $values = [
    'non_existent_field' => ['en' => 'Some Value']
  ];

  // التأكد من أن الكود يرمي Exception بالرسالة المحددة
  expect(fn() => $this->repository->bulkInsert($entry->id, $this->dataType->id, $values))
    ->toThrow(Exception::class, 'Field non_existent_field does not exist in this data type.');
});

test('replacePartial throws exception if field does not exist', function () {
  $entry = DataEntry::factory()->create(['data_type_id' => $this->dataType->id]);

  // مسار التغطية: if (! isset($fields[$fieldSlug])) -> throw Exception
  expect(fn() => $this->repository->replacePartial($entry->id, $this->dataType->id, [
    'invalid_fake_field' => 'Some Value'
  ]))->toThrow(Exception::class, 'Field invalid_fake_field does not exist in this data type.');
});

test('replacePartial deletes all languages if changed to non-translatable', function () {
  $entry = DataEntry::factory()->create(['data_type_id' => $this->dataType->id]);

  // إعداد: حقل له قيمتين بلغتين مختلفتين
  DB::table('data_entry_values')->insert([
    ['data_entry_id' => $entry->id, 'data_type_field_id' => $this->field->id, 'language' => 'en', 'value' => 'EN Val'],
    ['data_entry_id' => $entry->id, 'data_type_field_id' => $this->field->id, 'language' => 'ar', 'value' => 'AR Val'],
  ]);

  // مسار التغطية: $isNonTranslatable = true (لأننا نرسل قيمة واحدة بلغة null)
  // سيدخل في: if ($isNonTranslatable) { $query->delete(); }
  $this->repository->replacePartial($entry->id, $this->dataType->id, [
    $this->field->name => [null => 'New Generic Value']
  ]);

  // التحقق من أنه حذف اللغات القديمة وأضاف القيمة الجديدة
  $this->assertDatabaseMissing('data_entry_values', ['language' => 'en']);
  $this->assertDatabaseMissing('data_entry_values', ['language' => 'ar']);
  $this->assertDatabaseHas('data_entry_values', ['language' => null, 'value' => 'New Generic Value']);
});

test('replacePartial covers nested else branch with null and specific languages and arrays', function () {
  $entry = DataEntry::factory()->create(['data_type_id' => $this->dataType->id]);

  // إعداد بيانات قديمة
  DB::table('data_entry_values')->insert([
    ['data_entry_id' => $entry->id, 'data_type_field_id' => $this->field->id, 'language' => null, 'value' => 'Old Null Val'],
    ['data_entry_id' => $entry->id, 'data_type_field_id' => $this->field->id, 'language' => 'en', 'value' => 'Old EN Val'],
  ]);

  // 🔥 الخدعة هنا: نرسل مصفوفة فيها أكثر من لغة (ليكون count > 1)
  // إحداها null (لتغطية whereNull) والأخرى 'en' (لتغطية where('language', lang))
  // ونرسل قيمة 'en' كمصفوفة (لتغطية السطر: $valueList = is_array($value) ? $value : [$value])
  $this->repository->replacePartial($entry->id, $this->dataType->id, [
    $this->field->name => [
      '' => 'New Null Val', // في PHP مفتاح null يصبح '' والذي سيتحول إلى null عبر normalizeLang
      'en' => ['New EN Val 1', 'New EN Val 2'] // مصفوفة لتغطية حلقة الـ foreach الداخلية
    ]
  ]);

  // التحقق من الحذف الصحيح
  $this->assertDatabaseMissing('data_entry_values', ['value' => 'Old Null Val']);
  $this->assertDatabaseMissing('data_entry_values', ['value' => 'Old EN Val']);

  // التحقق من الإدخال الصحيح
  $this->assertDatabaseHas('data_entry_values', [
    'data_entry_id' => $entry->id,
    'language' => null,
    'value' => 'New Null Val'
  ]);
  $this->assertDatabaseHas('data_entry_values', [
    'data_entry_id' => $entry->id,
    'language' => 'en',
    'value' => 'New EN Val 1'
  ]);
  $this->assertDatabaseHas('data_entry_values', [
    'data_entry_id' => $entry->id,
    'language' => 'en',
    'value' => 'New EN Val 2'
  ]);
});

test('replacePartial handles null language correctly', function () {
  $entry = DataEntry::factory()->create(['data_type_id' => $this->dataType->id]);

  // إدخال قيمة بلغة null (حقل غير مترجم)
  DB::table('data_entry_values')->insert([
    'data_entry_id' => $entry->id,
    'data_type_field_id' => $this->field->id,
    'language' => null, // هنا الـ null
    'value' => 'Original Value',
    'created_at' => now(),
    'updated_at' => now(),
  ]);

  // استبدال القيمة
  $this->repository->replacePartial($entry->id, $this->dataType->id, [
    'title' => [null => 'New Value'] // إرسال null كلغة
  ]);

  // التحقق من أن القيمة الجديدة موجودة وتم استبدالها بـ whereNull
  $this->assertDatabaseHas('data_entry_values', [
    'data_entry_id' => $entry->id,
    'data_type_field_id' => $this->field->id,
    'language' => null,
    'value' => 'New Value'
  ]);
});

test('replacePartial deletes existing localized values correctly', function () {
  $entry = DataEntry::factory()->create(['data_type_id' => $this->dataType->id]);

  // 1. إدخال قيمة موجودة باللغة الإنجليزية
  DB::table('data_entry_values')->insert([
    'data_entry_id' => $entry->id,
    'data_type_field_id' => $this->field->id,
    'language' => 'en',
    'value' => 'Old English Value',
    'created_at' => now(),
    'updated_at' => now(),
  ]);

  // 2. استبدال القيمة بنفس اللغة 'en'
  // هذا سيجبر الكود على الدخول في مسار الـ else الخاص بـ $normalizedLang !== null
  $this->repository->replacePartial($entry->id, $this->dataType->id, [
    'title' => ['en' => 'New English Value']
  ]);

  // 3. التحقق من النتيجة
  $this->assertDatabaseHas('data_entry_values', [
    'data_entry_id' => $entry->id,
    'language' => 'en',
    'value' => 'New English Value'
  ]);

  $this->assertDatabaseMissing('data_entry_values', [
    'value' => 'Old English Value'
  ]);
});
