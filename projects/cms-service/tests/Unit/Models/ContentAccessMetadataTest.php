<?php

use App\Models\ContentAccessMetadata;
use App\Models\ContentAccessFeature;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('it can create metadata with all required fields', function () {
  $metadata = ContentAccessMetadata::create([
    'content_type' => 'article',
    'content_id' => 123,
    'requires_subscription' => true,
    'is_active' => true,
  ]);

  expect($metadata->content_type)->toBe('article')
    ->and($metadata->requires_subscription)->toBeTrue();
});

test('it can manage allowed feature keys through relations', function () {
  $metadata = ContentAccessMetadata::create([
    'content_type' => 'video',
    'content_id' => 456,
  ]);

  // إضافة ميزات عبر العلاقة
  $metadata->features()->create(['feature_key' => 'premium']);
  $metadata->features()->create(['feature_key' => 'hd-access']);

  // اختبار الدالة allowedFeatureKeys
  $keys = $metadata->fresh()->allowedFeatureKeys();

  expect($keys)->toContain('premium', 'hd-access')
    ->and($metadata->requiresFeature())->toBeTrue();
});

test('it can check if a specific feature is allowed', function () {
  $metadata = ContentAccessMetadata::create([
    'content_type' => 'post',
    'content_id' => 789,
  ]);

  $metadata->features()->create(['feature_key' => 'beta-access']);

  expect($metadata->allowsFeature('beta-access'))->toBeTrue()
    ->and($metadata->allowsFeature('free-access'))->toBeFalse();
});
