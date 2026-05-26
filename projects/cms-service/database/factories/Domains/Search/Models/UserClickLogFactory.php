<?php

namespace Database\Factories\Domains\Search\Models;

use App\Domains\Search\Models\UserClickLog;
use App\Domains\Search\Models\UserSearchLog;
use App\Models\User;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

class UserClickLogFactory extends Factory
{
  protected $model = UserClickLog::class;

  public function definition(): array
  {
    return [
      'user_id'         => User::factory(),
      'project_id'      => Project::factory(),
      'search_log_id'   => UserSearchLog::factory(),
      'entry_id'        => $this->faker->uuid(),
      'data_type_id'    => $this->faker->numberBetween(1, 5),
      'result_position' => $this->faker->numberBetween(1, 20),
      'session_id'      => $this->faker->uuid(),
      'clicked_at'      => now(),
    ];
  }
}
