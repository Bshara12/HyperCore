<?php

namespace Database\Factories;

use App\Models\DataEntry;
use App\Models\DataTypeField;
use App\Models\DataEntryValue;
use Illuminate\Database\Eloquent\Factories\Factory;

class DataEntryValueFactory extends Factory
{
    protected $model = DataEntryValue::class;

    public function definition(): array
    {
        return [
            // ربط القيمة بمدخل (Entry) وحقل (Field) موجودين
            'data_entry_id' => DataEntry::factory(),
            'data_type_field_id' => DataTypeField::factory(),
            
            // توليد لغة عشوائية (مثلاً: ar, en)
            'language' => $this->faker->randomElement(['ar', 'en']),
            
            // نص عشوائي للقيمة
            'value' => $this->faker->text(),
        ];
    }
}