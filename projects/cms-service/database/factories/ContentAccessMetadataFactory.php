<?php

namespace Database\Factories;

use App\Models\ContentAccessMetadata;
use App\Models\Project; // تأكد من استيراد موديل المشروع
use Illuminate\Database\Eloquent\Factories\Factory;

class ContentAccessMetadataFactory extends Factory
{
  /**
   * اسم الموديل الذي يرتبط به الـ Factory.
   */
  protected $model = ContentAccessMetadata::class;

  /**
   * تعريف البيانات الوهمية.
   */
  public function definition(): array
  {
    return [
      // إنشاء مشروع جديد تلقائياً في حال لم يتم تحديد project_id
      'project_id' => Project::factory(),

      'content_type' => $this->faker->randomElement(['Article', 'Video', 'Course', 'Lesson']),
      'content_id' => $this->faker->unique()->numberBetween(1, 9999), // تأكد من استخدام unique()
      'requires_subscription' => $this->faker->boolean(30), // 30% فقط تتطلب اشتراك

      'metadata' => [
        'tags' => $this->faker->words(3),
        'created_by' => $this->faker->userName(),
      ],

      'is_active' => true,
    ];
  }
}
