<?php

namespace Database\Factories;

use App\Models\SubscriptionAccessRule;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

class SubscriptionAccessRuleFactory extends Factory
{
  protected $model = SubscriptionAccessRule::class;

  public function definition(): array
  {
    return [
      'project_id'            => Project::factory(),
      'event_key'             => $this->faker->unique()->slug(),
      'requires_subscription' => true,
      'required_feature_key'  => $this->faker->randomElement(['api_access', 'export_data', 'advanced_analytics']),
      'is_active'             => true,
      'metadata'              => ['allowed_roles' => ['admin', 'editor']],
    ];
  }
}
