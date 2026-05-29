<?php

namespace Tests\Unit\Domains\CMS\Read\Repositories\Field;

use App\Domains\CMS\Read\Repositories\Field\FieldRepositoryRead;
use App\Models\DataType;
use App\Models\DataTypeField;
use App\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
  $this->repository = new FieldRepositoryRead();

  // إنشاء مشروع ببيانات اختبار
  $this->project = Project::factory()->create([
    'supported_languages' => ['en', 'ar']
  ]);

  // إنشاء DataType مرتبط بالمشروع
  $this->dataType = DataType::factory()->create([
    'project_id' => $this->project->id
  ]);
});

test('it lists fields and injects supported languages if translatable', function () {
  // 1. إنشاء حقل قابل للترجمة
  $translatableField = DataTypeField::factory()->create([
    'data_type_id' => $this->dataType->id,
    'translatable' => true
  ]);

  // 2. إنشاء حقل غير قابل للترجمة
  $nonTranslatableField = DataTypeField::factory()->create([
    'data_type_id' => $this->dataType->id,
    'translatable' => false
  ]);

  $results = $this->repository->list($this->dataType);

  // التحقق من أن الحقل القابل للترجمة حصل على اللغات
  $field1 = $results->firstWhere('id', $translatableField->id);
  expect($field1->supported_languages)->toBe(['en', 'ar']);

  // التحقق من أن الحقل غير القابل للترجمة لم يحصل على اللغات (أو بقيت كما هي)
  $field2 = $results->firstWhere('id', $nonTranslatableField->id);
  expect(isset($field2->supported_languages))->toBeFalse();
});

test('it retrieves only trashed fields for a data type', function () {
  // 1. حقل محذوف (Soft Delete)
  $trashedField = DataTypeField::factory()->create([
    'data_type_id' => $this->dataType->id
  ]);
  $trashedField->delete();

  // 2. حقل نشط
  DataTypeField::factory()->create([
    'data_type_id' => $this->dataType->id
  ]);

  $results = $this->repository->indexTrashed($this->dataType);

  expect($results)->toHaveCount(1)
    ->and($results->first()->id)->toBe($trashedField->id);
});
