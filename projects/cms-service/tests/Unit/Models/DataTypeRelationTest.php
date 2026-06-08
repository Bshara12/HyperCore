<?php

use App\Models\DataTypeRelation;
use App\Models\DataType;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('it correctly maps relationships to distinct data types', function () {
  $dataType = DataType::factory()->create();
  $relatedType = DataType::factory()->create();

  $relation = DataTypeRelation::factory()->create([
    'data_type_id' => $dataType->id,
    'related_data_type_id' => $relatedType->id
  ]);

  // اختبار العلاقة الأساسية
  expect($relation->dataType)->toBeInstanceOf(DataType::class)
    ->and($relation->dataType->id)->toBe($dataType->id);

  // اختبار العلاقة المرتبطة (يجب التأكد من استدعاء العمود المخصص)
  expect($relation->relatedDataType)->toBeInstanceOf(DataType::class)
    ->and($relation->relatedDataType->id)->toBe($relatedType->id);
});

test('it confirms correct table name and mass assignment', function () {
  // 1. أنشئ الـ DataTypes المطلوبة أولاً
  $type1 = DataType::factory()->create();
  $type2 = DataType::factory()->create();

  // 2. استخدم معرفات حقيقية موجودة في قاعدة البيانات
  $data = [
    'data_type_id'         => $type1->id,
    'related_data_type_id' => $type2->id,
    'relation_type'        => 'belongsToMany',
    'relation_name'        => 'categories',
    'pivot_table'          => 'data_type_categories'
  ];

  $relation = DataTypeRelation::create($data);

  expect($relation->getTable())->toBe('data_type_relations')
    ->and($relation->relation_name)->toBe('categories')
    ->and($relation->pivot_table)->toBe('data_type_categories');
});
