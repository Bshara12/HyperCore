<?php

use App\Models\SubscriptionUsage;
use App\Models\Subscription;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('it has subscription relationship', function () {
  $usage = SubscriptionUsage::factory()->create();

  expect($usage->subscription)->toBeInstanceOf(Subscription::class);
});

test('it increments usage value correctly', function () {
  $usage = SubscriptionUsage::factory()->create(['used_value' => 5]);

  // تنفيذ دالة الـ increment
  $usage->incrementUsage(3);

  // التأكد من أن القيمة أصبحت 8 (5 + 3)
  expect($usage->fresh()->used_value)->toBe(8);
});
