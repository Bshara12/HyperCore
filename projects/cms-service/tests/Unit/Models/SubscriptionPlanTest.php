<?php

use App\Models\SubscriptionPlan;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('it identifies free plans correctly', function () {
  $freePlan = SubscriptionPlan::factory()->free()->create();
  $paidPlan = SubscriptionPlan::factory()->create(['price' => 19.99]);

  expect($freePlan->isFree())->toBeTrue()
    ->and($paidPlan->isFree())->toBeFalse();
});

test('it has features relationship', function () {
  $plan = SubscriptionPlan::factory()->create();

  expect($plan->features())->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\HasMany::class);
});

test('it has subscriptions relationship', function () {
    $plan = SubscriptionPlan::factory()->create();
    
    // التحقق من نوع العلاقة
    expect($plan->subscriptions())->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\HasMany::class);
});
