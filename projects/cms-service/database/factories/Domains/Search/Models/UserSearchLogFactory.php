<?php

namespace Database\Factories\Domains\Search\Models;

use App\Domains\Search\Models\UserSearchLog;
use App\Models\User;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

class UserSearchLogFactory extends Factory
{
  protected $model = UserSearchLog::class;

  public function definition(): array
  {
    return [
      'user_id'           => User::factory(),
      'project_id'        => Project::factory(),
      'keyword'           => $this->faker->words(3, true),
      'language'          => 'en',
      'detected_intent'   => $this->faker->randomElement(['informational', 'transactional', 'navigational']),
      'intent_confidence' => $this->faker->randomFloat(3, 0.5, 1),
      'results_count'     => $this->faker->numberBetween(0, 100),
      'session_id'        => $this->faker->uuid(),
      'searched_at'       => now(),
    ];
  }
}
