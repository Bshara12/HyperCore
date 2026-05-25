<?php

use App\Models\ContentAccessFeature;
use App\Models\ContentAccessMetadata;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * نقوم بتعريف وظيفة مساعدة داخل الاختبار لإنشاء Metadata
 * لتجنب تكرار الكود وضمان ملء الحقول الإجبارية
 */
function createMetadata()
{
  return ContentAccessMetadata::create([
    // أضف هنا أي أعمدة إجبارية أخرى موجودة في جدولك
    'content_type' => 'article',
    'content_id' => 1
  ]);
}

test('it can create a content access feature', function () {
  // 1. إنشاء سجل Metadata (الأب)
  $metadata = createMetadata();

  // 2. إنشاء الميزة (الابن)
  $feature = ContentAccessFeature::create([
    'content_access_metadata_id' => $metadata->id,
    'feature_key' => 'premium-access',
  ]);

  // 3. التحقق من النتائج
  expect($feature->feature_key)->toBe('premium-access')
    ->and($feature->contentAccess->id)->toBe($metadata->id);
});

test('it belongs to content access metadata', function () {
  // 1. إنشاء سجل Metadata
  $metadata = createMetadata();

  // 2. إنشاء ميزة مرتبطة
  $feature = ContentAccessFeature::create([
    'content_access_metadata_id' => $metadata->id,
    'feature_key' => 'beta-tester',
  ]);

  // 3. التأكد من أن العلاقة تعيد الموديل الصحيح
  expect($feature->contentAccess)
    ->toBeInstanceOf(ContentAccessMetadata::class)
    ->and($feature->contentAccess->id)->toBe($metadata->id);
});
