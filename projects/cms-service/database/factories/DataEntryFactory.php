<?php

namespace Database\Factories;

use App\Models\DataEntry;
use App\Models\DataType;
use Illuminate\Database\Eloquent\Factories\Factory;

class DataEntryFactory extends Factory
{
    protected $model = DataEntry::class;

    public function definition()
    {
        return [
            'status' => 'draft',
            'scheduled_at' => null,
            'published_at' => null,
            'data_type_id' => DataType::factory(),
            'project_id' => fn (array $attrs) =>
                DataType::find($attrs['data_type_id'])->project_id,
        ];
    }
}