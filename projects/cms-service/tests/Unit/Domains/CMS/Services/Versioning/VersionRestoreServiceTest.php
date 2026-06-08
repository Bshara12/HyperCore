<?php

use App\Domains\CMS\Services\Versioning\VersionCreator;
use App\Domains\CMS\Services\Versioning\VersionRestoreService;
use App\Models\DataEntry;
use App\Models\DataEntryVersion;
use App\Models\DataType;
use App\Models\DataTypeField;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
  // محاكاة VersionCreator لأننا نختبر الاستعادة وليس إنشاء النسخة
  $this->versionCreator = Mockery::mock(VersionCreator::class);
  $this->service = new VersionRestoreService($this->versionCreator);
});

test('it restores entries using modern row format', function () {
  // 1. التجهيز
  $dataType = DataType::factory()->create();
  $field = DataTypeField::factory()->create(['data_type_id' => $dataType->id]);

  $entry = DataEntry::factory()->create(['data_type_id' => $dataType->id, 'status' => 'draft']);

  $snapshot = [
    'entry' => ['status' => 'published', 'scheduled_at' => null, 'published_at' => now()],
    'values' => [
      ['data_type_field_id' => $field->id, 'language' => 'en', 'value' => 'New Value']
    ]
  ];

  $version = DataEntryVersion::factory()->create([
    'data_entry_id' => $entry->id,
    'snapshot' => $snapshot
  ]);

  // 2. التوقعات
  $this->versionCreator->shouldReceive('create')->once();

  // 3. التنفيذ
  $this->service->restore($version->id);

  // 4. التأكيدات
  $entry->refresh();
  expect($entry->status)->toBe('published');

  $this->assertDatabaseHas('data_entry_values', [
    'data_entry_id' => $entry->id,
    'value' => 'New Value',
    'language' => 'en'
  ]);
});

test('it restores entries using legacy associative map format', function () {
  // 1. التجهيز (Legacy format يعتمد على وجود حقول في قاعدة البيانات)
  $dataType = DataType::factory()->create();
  $field = DataTypeField::factory()->create([
    'data_type_id' => $dataType->id,
    'name' => 'title' // الاسم المستخدم في الـ key
  ]);

  $entry = DataEntry::factory()->create(['data_type_id' => $dataType->id]);

  $snapshot = [
    'entry' => ['status' => 'published'],
    'values' => ['title_en' => 'Legacy Title'] // سيكتشف النظام 'title' كـ field و 'en' كـ lang
  ];

  $version = DataEntryVersion::factory()->create([
    'data_entry_id' => $entry->id,
    'snapshot' => $snapshot
  ]);

  $this->versionCreator->shouldReceive('create')->once();

  // 2. التنفيذ
  $this->service->restore($version->id);

  // 3. التأكيدات
  $this->assertDatabaseHas('data_entry_values', [
    'data_entry_id' => $entry->id,
    'data_type_field_id' => $field->id,
    'value' => 'Legacy Title',
    'language' => 'en'
  ]);
});

test('it throws exception if version not found', function () {
  $this->service->restore(999);
})->throws(\Illuminate\Database\Eloquent\ModelNotFoundException::class);

test('it throws type error if snapshot values is not an array', function () {
  // ننشئ نسخة تحتوي على قيم ليست مصفوفة
  $version = DataEntryVersion::factory()->create([
    'snapshot' => ['values' => 'not-an-array']
  ]);

  $this->service->restore($version->id);
})->throws(\TypeError::class, 'Invalid snapshot values format: expected array.');

test('it skips non-string keys and logs warning when field is not found in legacy format', function () {
  // 1. التجهيز
  Log::shouldReceive('warning')->once(); // التأكد من تسجيل التحذير

  $dataType = DataType::factory()->create();
  $entry = DataEntry::factory()->create(['data_type_id' => $dataType->id]);

  // Snapshot يحتوي على:
  // 1. مفتاح رقمي (غير نصي) ليغطي الخط 75
  // 2. مفتاح نصي لا يوجد له حقل في قاعدة البيانات ليغطي الخط 97
  $snapshot = [
    'values' => [
      0 => 'InvalidKey',
      'unknown_field_en' => 'Value'
    ]
  ];

  $version = DataEntryVersion::factory()->create([
    'data_entry_id' => $entry->id,
    'snapshot' => $snapshot
  ]);

  $this->versionCreator->shouldReceive('create')->once();

  // 2. التنفيذ
  $this->service->restore($version->id);

  // 3. التأكيد
  // يجب أن يكون الجدول فارغاً لأننا لم نقم بإنشاء حقول مطابقة في الـ setup
  $this->assertDatabaseCount('data_entry_values', 0);
});
