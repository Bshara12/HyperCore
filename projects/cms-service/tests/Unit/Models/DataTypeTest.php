<?php

use App\Models\DataCollection;
use App\Models\DataEntry;
use App\Models\DataType;
use App\Models\DataTypeField;
use App\Models\DataTypeRelation;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('it covers all relationships correctly', function () {
    // إنشاء الـ DataType (قد يقوم بإنشاء علاقات افتراضية تلقائياً)
    $dataType = DataType::factory()->create();

    // 1. اختبار علاقة project (BelongsTo)
    expect($dataType->project())->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\BelongsTo::class);

    // 2. اختبار علاقة collections (HasMany)
    $initialCollections = $dataType->collections()->count();
    DataCollection::factory()->create(['data_type_id' => $dataType->id]);
    expect($dataType->refresh()->collections)->toHaveCount($initialCollections + 1);

    // 3. اختبار علاقة fields (HasMany) - هنا كانت المشكلة وحُلّت ديناميكياً
    $initialFields = $dataType->fields()->count();
    DataTypeField::factory()->create(['data_type_id' => $dataType->id]);
    expect($dataType->refresh()->fields)->toHaveCount($initialFields + 1);

    // 4. اختبار علاقة entries (HasMany)
    $initialEntries = $dataType->entries()->count();
    DataEntry::factory()->create(['data_type_id' => $dataType->id]);
    expect($dataType->refresh()->entries)->toHaveCount($initialEntries + 1);

    // 5. اختبار علاقة relations (HasMany)
    $initialRelations = $dataType->relations()->count();
    DataTypeRelation::factory()->create(['data_type_id' => $dataType->id]);
    expect($dataType->refresh()->relations)->toHaveCount($initialRelations + 1);

    // 6. اختبار علاقة relatedRelations (HasMany مع مفتاح أجنبي مخصص)
    $initialRelated = $dataType->relatedRelations()->count();
    DataTypeRelation::factory()->create(['related_data_type_id' => $dataType->id]);
    expect($dataType->refresh()->relatedRelations)->toHaveCount($initialRelated + 1);
});

test('it covers remaining methods', function () {
    $dataType = DataType::factory()->create(['slug' => 'test-slug']);
    
    // اختبار getRouteKeyName
    expect($dataType->getRouteKeyName())->toBe('slug');
    
    // اختبار SoftDeletes
    $dataType->delete();
    expect($dataType->trashed())->toBeTrue();
});