<?php

namespace Database\Factories;

use App\Models\DataEntry;
use App\Models\DataTypeRelation;
use App\Models\DataEntryRelation; // الموديل الصحيح
use Illuminate\Database\Eloquent\Factories\Factory;

class DataEntryRelationFactory extends Factory
{
  protected $model = DataEntryRelation::class;

  public function definition(): array
  {
    return [
      'data_entry_id'         => DataEntry::factory(),
      'related_entry_id'      => DataEntry::factory(),
      'data_type_relation_id' => DataTypeRelation::factory(),
    ];
  }
}
