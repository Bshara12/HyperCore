<?php

namespace Database\Factories;

use App\Models\SubscriptionFeatureRule;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

class SubscriptionFeatureRuleFactory extends Factory
{
  protected $model = SubscriptionFeatureRule::class;

  public function definition(): array
  {
    return [
      'project_id'  => Project::factory(),
      'event_key'   => $this->faker->randomElement(['ai.generate', 'article.create', 'video.upload']),
      'feature_key' => $this->faker->randomElement(['ai_requests', 'monthly_articles', 'storage_limit']),
      'action'      => $this->faker->randomElement(['check', 'increment', 'both']),
      'reset_type'  => $this->faker->randomElement(['never', 'daily', 'monthly', 'yearly']),
      'is_active'   => true,
      'metadata'    => ['threshold' => 10, 'error_message' => 'Limit reached'],
    ];
  }
}
