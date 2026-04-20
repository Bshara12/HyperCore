<?php

namespace Database\Factories;

use App\Models\DataEntryVersion;
use App\Models\DataEntry;
use Illuminate\Database\Eloquent\Factories\Factory;

class DataEntryVersionFactory extends Factory
{
    protected $model = DataEntryVersion::class;

    public function definition(): array
    {
        return [
            'data_entry_id' => DataEntry::factory(),

            // ✅ مهم جداً
            'version_number' => 1,

            'snapshot' => [
                'entry' => [
                    'status' => 'draft',
                    'scheduled_at' => null,
                    'published_at' => null,
                ],
                'values' => []
            ],
        ];
    }
}
