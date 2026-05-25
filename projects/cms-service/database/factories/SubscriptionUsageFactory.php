<?php

namespace Database\Factories;

use App\Models\SubscriptionUsage;
use App\Models\Subscription;
use Illuminate\Database\Eloquent\Factories\Factory;

class SubscriptionUsageFactory extends Factory
{
  protected $model = SubscriptionUsage::class;

  public function definition(): array
  {
    return [
      'subscription_id' => Subscription::factory(),
      'feature_key'     => $this->faker->randomElement(['api_requests', 'storage_gb', 'team_members']),
      'used_value'      => $this->faker->numberBetween(0, 100),
      'reset_at'        => now()->addMonth(),
    ];
  }
}
