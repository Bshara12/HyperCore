<?php

use App\Domains\CMS\Services\Versioning\SnapshotGenerator;
use App\Models\DataEntry;
use App\Models\DataEntryValue;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->generator = new SnapshotGenerator();
});

test('it generates a snapshot with correct entry and values structure', function () {
    // 1. إنشاء المكونات الأساسية (التي تعتمد عليها العلاقات)
    $dataType = \App\Models\DataType::factory()->create();
    $dataTypeField = \App\Models\DataTypeField::factory()->create([
        'data_type_id' => $dataType->id
    ]);

    // 2. إنشاء الـ entry المرتبط بنفس نوع البيانات
    $entry = \App\Models\DataEntry::factory()->create([
        'data_type_id' => $dataType->id,
        'status' => 'published',
    ]);

    // 3. إنشاء القيمة باستخدام الـ ID الفعلي للحقل الذي أنشأناه
    $value = \App\Models\DataEntryValue::factory()->create([
        'data_entry_id' => $entry->id,
        'data_type_field_id' => $dataTypeField->id, // استخدم الـ ID الحقيقي
        'language' => 'ar',
        'value' => 'محتوى الاختبار'
    ]);

    // 4. التنفيذ والتأكد
    $snapshot = $this->generator->generate($entry);

    expect($snapshot['values'][0])->toMatchArray([
        'data_type_field_id' => $dataTypeField->id,
        'language' => 'ar',
        'value' => 'محتوى الاختبار',
    ]);
});

test('it handles empty values correctly', function () {
    $entry = DataEntry::factory()->create();

    $snapshot = $this->generator->generate($entry);

    expect($snapshot['values'])->toBeEmpty();
});