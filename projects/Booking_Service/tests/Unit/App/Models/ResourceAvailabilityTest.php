<?php

namespace Tests\Unit\App\Models;

use Tests\TestCase;
use App\Models\ResourceAvailability;
use App\Models\Resource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test; // استيراد الـ Attribute

class ResourceAvailabilityTest extends TestCase
{
  use RefreshDatabase;
  #[Test]
  public function it_has_correct_casts()
  {
    $availability = new ResourceAvailability([
      'day_of_week' => '1',
      'slot_duration' => '60',
      'is_active' => 1,
    ]);

    $this->assertIsInt($availability->day_of_week);
    $this->assertIsInt($availability->slot_duration);
    $this->assertIsBool($availability->is_active);
    $this->assertTrue($availability->is_active);
  }
  #[Test]
  public function it_belongs_to_a_resource()
  {
    $resource = Resource::factory()->create();

    // استخدام الإنشاء اليدوي لضمان العمل حتى بدون Factory للمودل الحالي
    $availability = ResourceAvailability::create([
      'resource_id' => $resource->id,
      'day_of_week' => 1,
      'start_time' => '09:00',
      'end_time' => '17:00',
      'slot_duration' => 60,
      'is_active' => true
    ]);

    $this->assertInstanceOf(Resource::class, $availability->resource);
    $this->assertEquals($resource->id, $availability->resource->id);
  }
  #[Test]
  public function it_returns_correct_day_name()
  {
    $availability = new ResourceAvailability(['day_of_week' => 0]);
    $this->assertEquals('Sunday', $availability->dayName());

    $availability->day_of_week = 6;
    $this->assertEquals('Saturday', $availability->dayName());

    // اختبار حالة اليوم غير المعروف (خارج نطاق 0-6) لتغطية الـ ?? 'Unknown'
    $availability->day_of_week = 10;
    $this->assertEquals('Unknown', $availability->dayName());
  }
  #[Test]
  public function it_calculates_slots_count_correctly()
  {
    // من 9 صباحاً حتى 11 صباحاً (120 دقيقة) / 60 دقيقة للـ slot = 2
    $availability = new ResourceAvailability([
      'start_time' => '09:00',
      'end_time' => '11:00',
      'slot_duration' => 60
    ]);

    $this->assertEquals(2, $availability->slotsCount());

    // تجربة حالة وجود كسر (يجب أن يتم التقريب للأسفل floor)
    // من 9:00 حتى 10:30 (90 دقيقة) / 60 دقيقة = 1.5 -> المتوقع 1
    $availability->end_time = '10:30';
    $this->assertEquals(1, $availability->slotsCount());
  }
}
