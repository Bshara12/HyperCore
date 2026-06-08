<?php

namespace Tests\Unit\Domains\CMS\Repositories;

use App\Domains\CMS\Repositories\Eloquent\EloquentDataEntryVersionRepository;
use App\Models\DataEntry; // تأكد من استيراد الموديل الصحيح
use App\Models\User;     // تأكد من استيراد الموديل الصحيح
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
  $this->repository = new EloquentDataEntryVersionRepository();
});

test('it can create a new data entry version with all data', function () {
  // 1. إنشاء البيانات الأساسية أولاً لتجنب خطأ المفتاح الأجنبي
  $entry = DataEntry::factory()->create();
  $user = User::factory()->create();

  $version = 5;
  $snapshot = ['field' => 'value', 'status' => 'published'];

  // 2. استخدم ID الذي تم إنشاؤه ديناميكياً
  $this->repository->create($entry->id, $version, $snapshot, $user->id);

  // 3. التحقق
  $this->assertDatabaseHas('data_entry_versions', [
    'data_entry_id' => $entry->id,
    'version_number' => $version,
    'snapshot' => json_encode($snapshot),
    'created_by' => $user->id,
  ]);
});

test('it can create a version with null user id', function () {
  // 1. إنشاء الـ Entry فقط (بما أن created_by قد يكون قابلاً للـ null)
  $entry = DataEntry::factory()->create();

  $version = 1;
  $snapshot = ['data' => 'empty'];

  // 2. استدعاء التابع مع null للـ user
  $this->repository->create($entry->id, $version, $snapshot, null);

  // 3. التحقق
  $this->assertDatabaseHas('data_entry_versions', [
    'data_entry_id' => $entry->id,
    'created_by' => null, // هنا نتحقق أن القيمة المخزنة هي null
  ]);
});
