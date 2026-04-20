<?php

namespace Database\Factories;

use App\Models\DataEntryValue;
use App\Models\DataEntry;
use App\Models\DataTypeField;
use Illuminate\Database\Eloquent\Factories\Factory;

class DataEntryValueFactory extends Factory
{
  protected $model = DataEntryValue::class;

  public function definition()
  {
    return [
      'data_entry_id' => null, // مهم جداً
      'data_type_field_id' => null,
      'language' => 'en',
      'value' => $this->faker->word(),
    ];
  }
}
