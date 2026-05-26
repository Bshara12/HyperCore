<?php

use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\SubscriptionUsage;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('it correctly identifies active status', function () {
  $sub = Subscription::factory()->create([
    'status' => Subscription::STATUS_ACTIVE,
    'ends_at' => now()->addDays(10)
  ]);

  expect($sub->isActive())->toBeTrue()
    ->and($sub->isExpired())->toBeFalse();
});

test('it correctly identifies expired status', function () {
  $sub = Subscription::factory()->create([
    'status' => Subscription::STATUS_ACTIVE,
    'ends_at' => now()->subDays(1)
  ]);

  expect($sub->isActive())->toBeFalse()
    ->and($sub->isExpired())->toBeTrue();
});

test('it correctly identifies cancelled status', function () {
  $sub = Subscription::factory()->create([
    'status' => Subscription::STATUS_CANCELLED
  ]);

  expect($sub->isCancelled())->toBeTrue();
});

// --- اختبارات العلاقات (Relationships) لتغطية 100% ---

test('it has a plan relationship', function () {
  $plan = SubscriptionPlan::factory()->create();
  $sub = Subscription::factory()->create(['plan_id' => $plan->id]);

  expect($sub->plan)->toBeInstanceOf(SubscriptionPlan::class)
    ->and($sub->plan->id)->toBe($plan->id);
});

test('it has usages relationship', function () {
  $sub = Subscription::factory()->create();
  // إنشاء استخدام واحد على الأقل للاختبار
  SubscriptionUsage::factory()->create(['subscription_id' => $sub->id]);

  expect($sub->usages)->toHaveCount(1)
    ->and($sub->usages->first())->toBeInstanceOf(SubscriptionUsage::class);
});
