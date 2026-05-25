<?php

namespace Database\Factories;

use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

class SubscriptionFactory extends Factory
{
  public function definition(): array
  {
    return [
      'user_id' => User::factory(),
      'project_id' => Project::factory(),
      'plan_id' => SubscriptionPlan::factory(),
      'status' => 'active',
      'starts_at' => now(),
      'ends_at' => now()->addMonth(),
      'auto_renew' => true,
    ];
  }
}
