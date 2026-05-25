<?php

namespace Database\Factories;

use App\Models\SearchSuggestion;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

class SearchSuggestionFactory extends Factory
{
  protected $model = SearchSuggestion::class;

  public function definition(): array
  {
    $keyword = $this->faker->word();
    return [
      'project_id' => Project::factory(),
      'keyword' => $keyword,
      'language' => 'en',
      'normalized_keyword' => strtolower($keyword),
      'search_count' => $this->faker->numberBetween(1, 100),
      'click_count' => $this->faker->numberBetween(0, 50),
      'score' => 0.0000,
      'last_searched_at' => now(),
    ];
  }
}
