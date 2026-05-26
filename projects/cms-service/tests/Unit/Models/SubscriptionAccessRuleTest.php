<?php

use App\Models\SubscriptionAccessRule;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('it creates access rule and casts attributes correctly', function () {
  $rule = SubscriptionAccessRule::factory()->create([
    'requires_subscription' => '1', // سيتم تحويله إلى boolean true
    'metadata' => ['key' => 'value']
  ]);

  // التأكد من أن الـ casts تعمل
  expect($rule->requires_subscription)->toBeTrue()
    ->and($rule->metadata)->toBe(['key' => 'value']);
});

test('it handles nullable required feature key', function () {
  $rule = SubscriptionAccessRule::factory()->create([
    'required_feature_key' => null
  ]);

  expect($rule->required_feature_key)->toBeNull();
});
