<?php

namespace Tests\Unit\App\Models;

use Tests\TestCase;
use App\Models\Booking;
use App\Models\Resource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Carbon\Carbon;
use PHPUnit\Framework\Attributes\Test; // استيراد الـ Attribute

class BookingTest extends TestCase
{
  use RefreshDatabase;
  #[Test]
  public function it_has_correct_casts_and_fillables()
  {
    $booking = new Booking([
      'start_at' => '2026-05-10 10:00:00',
      'amount' => '250.50',
      'status' => Booking::STATUS_PENDING
    ]);

    $this->assertInstanceOf(Carbon::class, $booking->start_at);
    $this->assertIsFloat($booking->amount);
    $this->assertEquals(250.50, $booking->amount);
  }
  #[Test]
  public function it_belongs_to_a_resource()
  {
    $resource = Resource::factory()->create();
    $booking = Booking::factory()->create(['resource_id' => $resource->id]);

    $this->assertInstanceOf(Resource::class, $booking->resource);
    $this->assertEquals($resource->id, $booking->resource->id);
  }
  #[Test]
  public function it_checks_confirmation_status()
  {
    $booking = new Booking(['status' => Booking::STATUS_CONFIRMED]);
    $this->assertTrue($booking->isConfirmed());

    $booking->status = Booking::STATUS_PENDING;
    $this->assertFalse($booking->isConfirmed());
  }
  #[Test]
  public function it_checks_if_booking_is_cancellable()
  {
    $booking = new Booking(['status' => Booking::STATUS_PENDING]);
    $this->assertTrue($booking->isCancellable());

    $booking->status = Booking::STATUS_CONFIRMED;
    $this->assertTrue($booking->isCancellable());

    $booking->status = Booking::STATUS_COMPLETED;
    $this->assertFalse($booking->isCancellable());
  }
  #[Test]
  public function it_checks_if_booking_is_reschedulable()
  {
    // حالةConfirmed وفي المستقبل
    $booking = new Booking([
      'status' => Booking::STATUS_CONFIRMED,
      'start_at' => now()->addDay()
    ]);
    $this->assertTrue($booking->isReschedulable());

    // حالة Confirmed ولكن في الماضي
    $booking->start_at = now()->subDay();
    $this->assertFalse($booking->isReschedulable());

    // حالة أخرى وفي المستقبل
    $booking->status = Booking::STATUS_PENDING;
    $booking->start_at = now()->addDay();
    $this->assertFalse($booking->isReschedulable());
  }
  #[Test]
  public function it_calculates_hours_until_booking()
  {
    $startTime = now()->addHours(5);
    $booking = new Booking(['start_at' => $startTime]);

    // نستخدم delta لأن الوقت يتحرك بالملي ثانية
    $this->assertEqualsWithDelta(5.0, $booking->hoursUntilBooking(), 0.1);
  }
  #[Test]
  public function it_calculates_duration_in_minutes()
  {
    $start = now();
    $end = now()->addMinutes(45);
    $booking = new Booking([
      'start_at' => $start,
      'end_at' => $end
    ]);

    $this->assertEquals(45, $booking->durationInMinutes());
  }
  #[Test]
  public function it_uses_soft_deletes()
  {
    $booking = Booking::factory()->create();
    $booking->delete();

    $this->assertSoftDeleted($booking);
  }
}
