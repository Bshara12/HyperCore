<?php

namespace Database\Factories;

use App\Models\PopularSearch;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

class PopularSearchFactory extends Factory
{
  protected $model = PopularSearch::class;

  public function definition(): array
  {
    return [
      'project_id' => Project::factory(),
      'keyword' => $this->faker->word(),
      'language' => 'en',
      'count_24h' => 0,
      'count_7d' => 0,
      'count_30d' => 0,
      'count_all_time' => 0,
      'trending_score' => 0.0,
      'alltime_score' => 0.0,
      'last_searched_at' => now(),
    ];
  }
}
