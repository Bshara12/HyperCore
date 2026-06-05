<?php

namespace Tests\Unit\Http\Resources;

use App\Http\Resources\RatingResource;
use App\Models\Rating;
use App\Models\User;
use App\Models\Project; // 🔥 استيراد موديل المشروع لتغطية العلاقة المتعددة
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// ─── 1. اختبار تحويل البيانات بدون تحميل علاقة المستخدم ────────────────────────
test('it transforms rating resource correctly without user relation loaded', function () {
  // Arrange: إنشاء مشروع أولاً لربطه بالتقييم
  $project = Project::factory()->create();

  $rating = Rating::factory()->create([
    'rating' => 5,
    'review' => 'Excellent service!',
    'rateable_id' => $project->id,
    'rateable_type' => $project->getMorphClass(), // جلب نوع الموديل بشكل ديناميكي لارافيل
  ]);

  // Act
  $resource = new RatingResource($rating);
  $data = $resource->resolve();

  // Assert
  expect($data)->toHaveKeys(['id', 'rating', 'review', 'created_at'])
    ->and($data['rating'])->toBe(5)
    ->and($data['review'])->toBe('Excellent service!')
    ->and($data)->not->toHaveKey('user');
});

// ─── 2. اختبار تحويل البيانات عند تحميل علاقة المستخدم (whenLoaded) ───────────
test('it includes user details when user relation is loaded', function () {
  // Arrange: إنشاء المستخدم والمشروع والتقييم
  $user = User::factory()->create([
    'name' => 'Ali Ahmad'
  ]);

  $project = Project::factory()->create();

  $rating = Rating::factory()->create([
    'user_id' => $user->id,
    'rating' => 4,
    'review' => 'Good product',
    'rateable_id' => $project->id,
    'rateable_type' => $project->getMorphClass(),
  ]);

  // 🔥 الحل السحري: إيهام الموديل بأن العلاقة مشحونة يدوياً دون الحاجة لوجود الدالة user()
  $rating->setRelation('user', $user);

  // Act
  $resource = new RatingResource($rating);
  $data = $resource->resolve();

  // Assert
  expect($data)->toHaveKey('user')
    ->and($data['user'])->toBe([
      'id' => $user->id,
      'name' => 'Ali Ahmad'
    ]);
});
