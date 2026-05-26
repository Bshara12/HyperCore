<?php

use App\Models\SubscriptionFeature;
use App\Models\SubscriptionPlan;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('it identifies feature types correctly', function () {
  $booleanFeature = SubscriptionFeature::factory()->create(['feature_type' => 'boolean']);
  $limitFeature = SubscriptionFeature::factory()->create(['feature_type' => 'limit']);

  expect($booleanFeature->isBoolean())->toBeTrue()
    ->and($limitFeature->isLimit())->toBeTrue()
    ->and($booleanFeature->isLimit())->toBeFalse();
});

test('it belongs to a plan', function () {
  $plan = SubscriptionPlan::factory()->create();
  $feature = SubscriptionFeature::factory()->create(['plan_id' => $plan->id]);

  expect($feature->plan)->toBeInstanceOf(SubscriptionPlan::class)
    ->and($feature->plan->id)->toBe($plan->id);
});
