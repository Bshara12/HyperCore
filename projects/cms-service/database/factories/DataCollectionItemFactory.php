<?php

namespace Database\Factories;

use App\Models\DataCollectionItem;
use App\Models\DataCollection;
use App\Models\DataEntry; // لاحظ هذا الموديل
use Illuminate\Database\Eloquent\Factories\Factory;

class DataCollectionItemFactory extends Factory
{
  protected $model = DataCollectionItem::class;

  public function definition()
  {
    return [
      'collection_id' => DataCollection::factory(),
      'item_id' => DataEntry::factory(), // تأكد أن لديك DataEntryFactory
      'sort_order' => $this->faker->numberBetween(0, 100),
    ];
  }
}
