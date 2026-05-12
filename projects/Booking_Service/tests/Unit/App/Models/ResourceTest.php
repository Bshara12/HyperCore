<?php

namespace Tests\Unit\App\Models;

use Tests\TestCase;
use App\Models\Resource;
use App\Models\ResourceAvailability;
use App\Models\BookingCancellationPolicy;
use App\Models\Booking;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test; // استيراد الـ Attribute

class ResourceTest extends TestCase
{
  use RefreshDatabase;
  #[Test]
  public function it_has_correct_casts()
  {
    $resource = new Resource([
      'capacity' => '10',
      'price' => '150.50',
      'settings' => ['color' => 'red'] // مرر مصفوفة هنا وليس JSON string
    ]);

    $this->assertIsInt($resource->capacity);
    $this->assertIsFloat($resource->price);
    $this->assertIsArray($resource->settings);
    $this->assertEquals('red', $resource->settings['color']);
  }
  #[Test]
  public function it_has_many_relationships()
  {
    $resource = Resource::factory()->create();

    // إنشاء بيانات مرتبطة للاختبار
    ResourceAvailability::create([
      'resource_id' => $resource->id,
      'day_of_week' => 1,
      'start_time' => '08:00',
      'end_time' => '17:00',
      'is_active' => true
    ]);

    BookingCancellationPolicy::create([
      'resource_id' => $resource->id,
      'hours_before' => 24,
      'refund_percentage' => 100
    ]);
    Booking::factory()->create(['resource_id' => $resource->id]);

    $this->assertInstanceOf(ResourceAvailability::class, $resource->availabilities->first());
    $this->assertInstanceOf(BookingCancellationPolicy::class, $resource->cancellationPolicies->first());
    $this->assertInstanceOf(Booking::class, $resource->bookings->first());
  }
  #[Test]
  public function it_filters_active_availabilities()
  {
    $resource = Resource::factory()->create();

    // سجل نشط وسجل غير نشط
    ResourceAvailability::create([
      'resource_id' => $resource->id,
      'day_of_week' => 1,
      'start_time' => '08:00',
      'end_time' => '17:00',
      'is_active' => true
    ]);
    ResourceAvailability::create([
      'resource_id' => $resource->id,
      'day_of_week' => 1,
      'start_time' => '08:00',
      'end_time' => '17:00',
      'is_active' => false
    ]);

    $this->assertCount(2, $resource->availabilities);
    $this->assertCount(1, $resource->activeAvailabilities);
  }
  #[Test]
  public function it_checks_status_helpers()
  {
    $resource = new Resource(['status' => Resource::STATUS_ACTIVE]);
    $this->assertTrue($resource->isActive());
    $this->assertTrue($resource->isBookable());

    $resource->status = Resource::STATUS_INACTIVE;
    $this->assertFalse($resource->isActive());
    $this->assertFalse($resource->isBookable());
  }
  #[Test]
  public function it_checks_payment_type_helpers()
  {
    $resource = new Resource(['payment_type' => Resource::PAYMENT_FREE]);
    $this->assertTrue($resource->isFree());
    $this->assertFalse($resource->isPaid());

    $resource->payment_type = Resource::PAYMENT_PAID;
    $this->assertTrue($resource->isPaid());
    $this->assertFalse($resource->isFree());
  }
  #[Test]
  public function it_returns_availability_for_specific_day()
  {
    $resource = Resource::factory()->create();

    // إضافة توفر ليوم الاثنين (1)
    $availability = ResourceAvailability::create([
      'resource_id' => $resource->id,
      'day_of_week' => 1,
      'start_time' => '08:00',
      'end_time' => '17:00',
      'is_active' => true
    ]);

    // 1. فحص يوم موجود
    $found = $resource->availabilityForDay(1);
    $this->assertNotNull($found);
    $this->assertEquals($availability->id, $found->id);

    // 2. فحص يوم غير موجود (يغطي حالة return null)
    $this->assertNull($resource->availabilityForDay(5));
  }
  #[Test]
  public function it_orders_cancellation_policies_by_hours_before_descending()
  {
    $resource = Resource::factory()->create();

    // إنشاء سياسة بـ 12 ساعة وأخرى بـ 24 ساعة
    BookingCancellationPolicy::create([
      'resource_id' => $resource->id,
      'hours_before' => 12,
      'refund_percentage' => 50
    ]);
    BookingCancellationPolicy::create([
      'resource_id' => $resource->id,
      'hours_before' => 24,
      'refund_percentage' => 100
    ]);

    $policies = $resource->cancellationPolicies;

    // يجب أن تكون الـ 24 ساعة هي الأولى بسبب orderByDesc
    $this->assertEquals(24, $policies->first()->hours_before);
  }
}
