<?php

namespace Database\Factories;

use App\Models\SearchIndex;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

class SearchIndexFactory extends Factory
{
  protected $model = SearchIndex::class;

  public function definition(): array
  {
    return [
      'entry_id' => $this->faker->unique()->numberBetween(1, 10000),
      'data_type_id' => 1,
      'project_id' => Project::factory(),
      'language' => 'en',
      'title' => $this->faker->sentence(),
      'content' => $this->faker->paragraphs(3, true),
      'meta' => ['tags' => ['tech', 'laravel']],
      'status' => 'published',
      'published_at' => now(),
      'click_count' => 0,
      'view_count' => 0,
      'popularity_score' => 0.0000,
    ];
  }
}
