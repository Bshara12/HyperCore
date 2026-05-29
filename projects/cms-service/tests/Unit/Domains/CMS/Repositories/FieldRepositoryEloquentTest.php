<?php

namespace Tests\Unit\Domains\CMS\Repositories;

use App\Domains\CMS\DTOs\Field\CreateFieldDTO;
use App\Domains\CMS\Repositories\Eloquent\FieldRepositoryEloquent;
use App\Models\DataType;
use App\Models\DataTypeField;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Exceptions\HttpResponseException;
use Symfony\Component\HttpKernel\Exception\HttpException;

uses(RefreshDatabase::class);

beforeEach(function () {
  $this->repository = new FieldRepositoryEloquent();
  $this->dataType = DataType::factory()->create();
});

test('ensureFieldIsUnique throws exception if field name exists', function () {
  DataTypeField::factory()->create([
    'data_type_id' => $this->dataType->id,
    'name' => 'test_field'
  ]);

  expect(fn() => $this->repository->ensureFieldIsUnique($this->dataType->id, 'test_field'))
    ->toThrow(HttpException::class, "Field 'test_field' already exists for this Data-Type.");
});

test('ensureUpdatedFieldIsUnique ignores current field ID', function () {
  $field = DataTypeField::factory()->create([
    'data_type_id' => $this->dataType->id,
    'name' => 'unique_field'
  ]);

  // يجب ألا يرمي استثناء لأنه نفس الحقل (نفس الـ ID)
  $this->repository->ensureUpdatedFieldIsUnique($this->dataType->id, 'unique_field', $field->id);
  expect(true)->toBeTrue();
});

test('create field creates a new record in database', function () {
  $dto = new CreateFieldDTO(
    data_type_id: $this->dataType->id,
    name: 'new_field',
    type: 'text',
    required: true,
    translatable: false,
    validation_rules: ['required'],
    settings: ['key' => 'value'],
    sort_order: 1
  );

  $field = $this->repository->create($dto, ['processed' => 'settings']);

  $this->assertDatabaseHas('data_type_fields', [
    'name' => 'new_field',
    'data_type_id' => $this->dataType->id,
    'settings' => json_encode(['processed' => 'settings'])
  ]);
});

test('update field updates existing record', function () {
  $field = DataTypeField::factory()->create(['data_type_id' => $this->dataType->id]);

  $dto = new CreateFieldDTO(
    data_type_id: $this->dataType->id,
    name: 'updated_name',
    type: 'text',
    required: false,
    translatable: true,
    validation_rules: [],
    settings: [],
    sort_order: 2
  );

  $this->repository->update($dto, $field, ['new' => 'settings']);

  $this->assertDatabaseHas('data_type_fields', [
    'id' => $field->id,
    'name' => 'updated_name',
    'translatable' => true
  ]);
});

test('getByDataType returns collection of fields', function () {
  // 🔥 الحل: تنظيف أي حقول افتراضية تم إنشاؤها تلقائياً بواسطة الـ Factory للـ DataType
  DataTypeField::where('data_type_id', $this->dataType->id)->delete();

  // الآن ننشئ الحقلين اللذين نريد اختبارهما فقط
  DataTypeField::factory()->count(2)->create(['data_type_id' => $this->dataType->id]);

  $fields = $this->repository->getByDataType($this->dataType->id);

  // سينجح الاختبار الآن لأن المجموع سيكون 2 بالضبط
  expect($fields)->toHaveCount(2);
});

test('soft delete, restore and force delete functionality', function () {
  $field = DataTypeField::factory()->create(['data_type_id' => $this->dataType->id]);

  // 1. الحذف اللين (Soft Delete)
  $this->repository->delete($field);
  $this->assertSoftDeleted('data_type_fields', ['id' => $field->id]);

  // 2. الاستعادة (Restore)
  $this->repository->restore($field->id);
  $this->assertDatabaseHas('data_type_fields', ['id' => $field->id, 'deleted_at' => null]);

  // 3. الحذف النهائي (Force Delete)
  $this->repository->forceDelete($field->id);
  $this->assertDatabaseMissing('data_type_fields', ['id' => $field->id]);
});

// 1. اختبار التابع findByDataTypeAndName (حالة النجاح - العثور على الحقل)
test('findByDataTypeAndName returns the field if it exists', function () {
  $field = DataTypeField::factory()->create([
    'data_type_id' => $this->dataType->id,
    'name' => 'unique_field'
  ]);

  $result = $this->repository->findByDataTypeAndName($this->dataType->id, 'unique_field');

  expect($result)->not->toBeNull()
    ->and($result->id)->toBe($field->id);
});

// 2. اختبار التابع findByDataTypeAndName (حالة عدم العثور - إرجاع null)
test('findByDataTypeAndName returns null if field does not exist', function () {
  $result = $this->repository->findByDataTypeAndName($this->dataType->id, 'non_existent_field');

  expect($result)->toBeNull();
});

// 3. اختبار التابع ensureUpdatedFieldIsUnique (حالة الخطأ - وجود تكرار)
test('ensureUpdatedFieldIsUnique throws exception if another field has the same name', function () {
  // ننشئ حقلاً موجوداً مسبقاً (هذا هو الحقل الذي سيتسبب في التكرار)
  DataTypeField::factory()->create([
    'data_type_id' => $this->dataType->id,
    'name' => 'taken_name'
  ]);

  // ننشئ الحقل الذي نحاول تحديثه الآن
  $fieldToUpdate = DataTypeField::factory()->create([
    'data_type_id' => $this->dataType->id,
    'name' => 'original_name'
  ]);

  // محاولة تغيير اسم الحقل الحالي إلى 'taken_name' يجب أن تفشل
  expect(fn() => $this->repository->ensureUpdatedFieldIsUnique(
    $this->dataType->id,
    'taken_name',
    $fieldToUpdate->id
  ))
    ->toThrow(HttpException::class, "Field 'taken_name' already exists for this Data-Type.");
});
