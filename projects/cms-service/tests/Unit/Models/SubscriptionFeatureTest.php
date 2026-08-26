<?php

use App\Models\SubscriptionFeature;
use App\Models\SubscriptionPlan;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('it identifies feature types correctly', function () {
  $booleanFeature = SubscriptionFeature::factory()->create(['feature_type' => 'boolean']);

  /*
   | 'number' هو النوع الذي يكتبه ويقرؤه النطاق كلّه — CheckUsageLimitAction
   | و CheckFeatureAccessAction و ProcessUsageEventAction. 'limit' لم يكن
   | يُكتَب قط، فكانت isLimit() ترجع false دائماً على بيانات حقيقية.
   */
  $limitFeature = SubscriptionFeature::factory()->create(['feature_type' => 'number']);

  expect($booleanFeature->isBoolean())->toBeTrue()
    ->and($limitFeature->isLimit())->toBeTrue()
    ->and($booleanFeature->isLimit())->toBeFalse()
    ->and($limitFeature->isBoolean())->toBeFalse();
});

test('it belongs to a plan', function () {
  $plan = SubscriptionPlan::factory()->create();
  $feature = SubscriptionFeature::factory()->create(['plan_id' => $plan->id]);

  expect($feature->plan)->toBeInstanceOf(SubscriptionPlan::class)
    ->and($feature->plan->id)->toBe($plan->id);
});
