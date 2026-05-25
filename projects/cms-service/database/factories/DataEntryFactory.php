<?php

namespace Database\Factories;

use App\Models\DataEntry;
use App\Models\Project;
use App\Models\DataType;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class DataEntryFactory extends Factory
{
  protected $model = DataEntry::class;

  public function definition(): array
  {
    return [
      'slug'          => $this->faker->unique()->slug(),
      'data_type_id'  => DataType::factory(),
      'project_id'    => Project::factory(),
      'status'        => 'draft',
      'created_by'    => User::factory(), // أو null إذا كان الحقل يقبل ذلك
      'ratings_count' => 0,
      'ratings_avg'   => 0.00,
    ];
  }

  // حالات (States) مفيدة للاختبارات
  public function published(): Factory
  {
    return $this->state(fn(array $attributes) => [
      'status' => 'published',
      'published_at' => now(),
    ]);
  }
}
