<?php

use App\Models\SubscriptionFeatureRule;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('it creates rule with correct constants', function () {
  $rule = SubscriptionFeatureRule::factory()->create([
    'action' => SubscriptionFeatureRule::ACTION_BOTH,
    'reset_type' => SubscriptionFeatureRule::RESET_MONTHLY
  ]);

  expect($rule->action)->toBe('both')
    ->and($rule->reset_type)->toBe('monthly')
    ->and($rule->is_active)->toBeTrue();
});

test('it handles metadata correctly', function () {
  $metadata = ['cost' => 5, 'priority' => 'high'];
  $rule = SubscriptionFeatureRule::factory()->create([
    'metadata' => $metadata
  ]);

  expect($rule->metadata)->toBe($metadata);
});
