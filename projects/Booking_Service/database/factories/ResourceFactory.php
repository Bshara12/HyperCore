<?php

namespace Database\Factories;

use App\Models\Resource;
use App\Models\ResourceAvailability;
use App\Models\BookingCancellationPolicy;
use Illuminate\Database\Eloquent\Factories\Factory;

class ResourceFactory extends Factory
{
  protected $model = Resource::class;

  public function definition(): array
  {
    return [
      'data_entry_id' => $this->faker->numberBetween(1, 1000),
      'project_id'    => $this->faker->numberBetween(1, 100),
      'name'          => $this->faker->words(3, true),
      'type'          => $this->faker->randomElement(['room', 'court', 'seat', 'doctor']),
      'capacity'      => $this->faker->numberBetween(1, 50),
      'status'        => 'active', // القيمة الافتراضية للنجاح في التستات
      'settings'      => [
        'wifi' => $this->faker->boolean(),
        'location' => $this->faker->city(),
        'color' => $this->faker->safeColorName(),
      ],
      'payment_type'  => 'free',
      'price'         => null,
    ];
  }

  /**
   * حالة مخصصة لإنشاء مورد مع كامل ملحقاته (Availability & Policies)
   * مفيدة جداً للوصول لتغطية 100% في اختبارات الـ Integration
   */
  public function withFullSetup(): static
  {
    return $this->afterCreating(function (Resource $resource) {
      // 1. إنشاء توافر لجميع أيام الأسبوع تلقائياً
      foreach (range(0, 6) as $day) {
        ResourceAvailability::create([
          'resource_id'   => $resource->id,
          'day_of_week'   => $day,
          'start_time'    => '08:00:00',
          'end_time'      => '22:00:00',
          'slot_duration' => 60,
          'is_active'     => true,
        ]);
      }

      // 2. إنشاء سياسة إلغاء افتراضية (مثلاً استرداد 100% قبل 24 ساعة)
      BookingCancellationPolicy::create([
        'resource_id'       => $resource->id,
        'hours_before'      => 24,
        'refund_percentage' => 100,
        'description'       => 'Full refund if cancelled 24h before',
      ]);
    });
  }

  /**
   * حالة مخصصة للموارد المدفوعة
   */
  public function paid(): static
  {
    return $this->state(fn(array $attributes) => [
      'payment_type' => 'paid',
      'price'        => $this->faker->randomFloat(2, 10, 500),
    ]);
  }

  /**
   * حالة مخصصة للموارد المعطلة
   */
  public function inactive(): static
  {
    return $this->state(fn(array $attributes) => [
      'status' => 'inactive',
    ]);
  }
}
