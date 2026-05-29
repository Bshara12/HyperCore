<?php

namespace Tests\Unit\Domains\Subscription\Repositories;

use App\Domains\Subscription\Repositories\Eloquent\EloquentContentAccessMetadataRepository;
use App\Models\ContentAccessMetadata;
use App\Models\ContentAccessFeature;
use App\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
  $this->repository = new EloquentContentAccessMetadataRepository();

  // إنشاء مشروع افتراضي لضمان تلبية قيد المفتاح الأجنبي (Foreign Key)
  $this->project = Project::factory()->create();
});

test('createWithFeatures persists metadata and syncs features correctly', function () {
  $data = [
    'project_id' => $this->project->id,
    'content_type' => 'video',
    'content_id' => 101,
    'is_active' => true,
  ];
  $features = ['hd_quality', 'offline_mode'];

  $metadata = $this->repository->createWithFeatures($data, $features);

  // التأكد من الحفظ في قاعدة البيانات
  $this->assertDatabaseHas('content_access_metadata', ['content_id' => 101]);
  expect($metadata->features)->toHaveCount(2);

  // التأكد من حفظ الـ Features في الجدول المرتبط
  $this->assertDatabaseHas('content_access_features', ['feature_key' => 'hd_quality']);
  $this->assertDatabaseHas('content_access_features', ['feature_key' => 'offline_mode']);
});

test('updateWithFeatures syncs features and removes old ones', function () {
  // 1. إنشاء سجل موجود مسبقاً مع ميزة قديمة
  $metadata = ContentAccessMetadata::factory()->create([
    'project_id' => $this->project->id,
    'content_id' => 202
  ]);
  ContentAccessFeature::create(['content_access_metadata_id' => $metadata->id, 'feature_key' => 'old_feature']);

  // 2. تحديث الميزات (إزالة old_feature وإضافة new_feature)
  $data = ['requires_subscription' => true];
  $newFeatures = ['new_feature'];

  $this->repository->updateWithFeatures($metadata, $data, $newFeatures);

  // 3. التحقق من التحديث
  $freshMetadata = $metadata->fresh(['features']);
  expect($freshMetadata->features)->toHaveCount(1);
  expect($freshMetadata->features->first()->feature_key)->toBe('new_feature');

  // التأكد من حذف الميزة القديمة
  $this->assertDatabaseMissing('content_access_features', ['feature_key' => 'old_feature']);
});

test('findContentRule eager loads features and returns metadata', function () {
  $metadata = ContentAccessMetadata::factory()->create([
    'project_id' => $this->project->id,
    'content_type' => 'article',
    'content_id' => 5
  ]);
  ContentAccessFeature::create(['content_access_metadata_id' => $metadata->id, 'feature_key' => 'premium']);

  $result = $this->repository->findContentRule('article', 5);

  expect($result)->not->toBeNull();
  expect($result->relationLoaded('features'))->toBeTrue();
  expect($result->features->first()->feature_key)->toBe('premium');
});

test('findManyRules retrieves multiple rules and keys them by content_id', function () {
  // حدد content_type يدوياً لضمان تطابقه مع طلب البحث
  $m1 = ContentAccessMetadata::factory()->create([
    'project_id' => $this->project->id,
    'content_id' => 10,
    'content_type' => 'video'
  ]);

  $m2 = ContentAccessMetadata::factory()->create([
    'project_id' => $this->project->id,
    'content_id' => 20,
    'content_type' => 'video'
  ]);

  $results = $this->repository->findManyRules('video', [10, 20]);

  expect($results)->toHaveCount(2);
  expect($results)->toHaveKey(10);
  expect($results)->toHaveKey(20);
});

test('disable updates the is_active status', function () {
  $metadata = ContentAccessMetadata::factory()->create([
    'project_id' => $this->project->id,
    'is_active' => true
  ]);

  $this->repository->disable($metadata);

  expect($metadata->fresh()->is_active)->toBeFalse();
});

test('create persists metadata in database', function () {
  $data = [
    'project_id' => $this->project->id,
    'content_type' => 'article',
    'content_id' => 999,
    'is_active' => true,
  ];

  $metadata = $this->repository->create($data);

  expect($metadata->id)->not->toBeNull();
  $this->assertDatabaseHas('content_access_metadata', ['content_id' => 999]);
});

test('findById returns metadata with features loaded', function () {
  $metadata = ContentAccessMetadata::factory()->create([
    'project_id' => $this->project->id
  ]);
  // إضافة ميزة للتأكد من أنها تظهر
  \App\Models\ContentAccessFeature::create([
    'content_access_metadata_id' => $metadata->id,
    'feature_key' => 'premium'
  ]);

  $found = $this->repository->findById($metadata->id);

  expect($found)->not->toBeNull();
  expect($found->id)->toBe($metadata->id);
  expect($found->relationLoaded('features'))->toBeTrue();
});

test('paginate returns paginated results', function () {
  // إنشاء 25 سجل للتأكد من عمل الـ pagination (الافتراضي 20)
  ContentAccessMetadata::factory()->count(25)->create(['project_id' => $this->project->id]);

  $results = $this->repository->paginate($this->project->id);

  expect($results)->toBeInstanceOf(\Illuminate\Pagination\LengthAwarePaginator::class);
  expect($results->total())->toBe(25);
  expect($results->items())->toHaveCount(20); // الصفحة الأولى
});

test('update updates scalar fields', function () {
  $metadata = ContentAccessMetadata::factory()->create([
    'project_id' => $this->project->id,
    'is_active' => true
  ]);

  $this->repository->update($metadata, ['is_active' => false]);

  expect($metadata->fresh()->is_active)->toBeFalse();
});
test('syncFeatures deletes existing features and does not insert new ones if array is empty', function () {
  // 1. تحضير سجل يحتوي على ميزة موجودة مسبقاً
  $metadata = ContentAccessMetadata::factory()->create([
    'project_id' => $this->project->id
  ]);
  \App\Models\ContentAccessFeature::create([
    'content_access_metadata_id' => $metadata->id,
    'feature_key' => 'old_feature'
  ]);

  // 2. تحديث الميزات بمصفوفة فارغة
  $this->repository->updateWithFeatures($metadata, [], []);

  // 3. التحقق: الميزة القديمة حُذفت، ولم يتم إضافة أي شيء جديد (العودة المبكرة عملت)
  $this->assertDatabaseCount('content_access_features', 0);
  expect($metadata->fresh()->features)->toHaveCount(0);
});

test('syncFeatures filters out empty strings and effectively clears list', function () {
  // 1. تحضير سجل
  $metadata = ContentAccessMetadata::factory()->create([
    'project_id' => $this->project->id
  ]);

  // 2. تحديث الميزات بمصفوفة تحتوي على نص فارغ فقط (يختبر الـ array_filter والـ empty check)
  $this->repository->updateWithFeatures($metadata, [], ['', ' ']);

  // 3. التحقق: لم يتم إدخال أي شيء لأن المصفوقة أصبحت فارغة بعد الـ filter
  $this->assertDatabaseCount('content_access_features', 0);
});
