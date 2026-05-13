<?php

namespace Tests\Integration\Domains\Booking\Services;

use Tests\TestCase;
use App\Models\Booking;
use App\Models\Resource;
use App\Models\ResourceAvailability;
use App\Domains\Booking\Services\SlotGeneratorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Carbon\Carbon;

class SlotGeneratorServiceTest extends TestCase
{
  use RefreshDatabase;

  private SlotGeneratorService $service;

  protected function setUp(): void
  {
    parent::setUp();
    $this->service = app(SlotGeneratorService::class);
  }

  /** @test */
  public function it_returns_empty_array_if_no_availability_for_the_day()
  {
    $resource = Resource::factory()->create();
    $date = Carbon::parse('2026-05-11');

    $result = $this->service->generate($resource, $date);

    $this->assertIsArray($result);
    $this->assertEmpty($result);
  }

  /** @test */
  public function it_generates_slots_correctly_with_availability()
  {
    $resource = Resource::factory()->create(['capacity' => 2]);
    $date = Carbon::parse('2026-06-01'); // Monday (1)

    // استخدام Query Builder للإنشاء مباشرة لتجنب خطأ الـ Factory
    ResourceAvailability::query()->create([
      'resource_id'   => $resource->id,
      'day_of_week'   => $date->dayOfWeek,
      'start_time'    => '10:00:00',
      'end_time'      => '11:00:00',
      'slot_duration' => 30,
      'is_active'     => true
    ]);

    Booking::factory()->create([
      'resource_id' => $resource->id,
      'start_at'    => '2026-06-01 10:00:00',
      'status'      => 'confirmed'
    ]);

    $result = $this->service->generate($resource, $date);

    $this->assertCount(2, $result);
    $this->assertEquals(1, $result[0]['booked_count']);
    $this->assertTrue($result[0]['available']);
  }

  /** @test */
  public function it_marks_past_slots_as_unavailable()
  {
    $resource = Resource::factory()->create(['capacity' => 10]);

    // تثبيت الوقت عند الساعة 12:00 ظهراً
    $today = Carbon::parse('2026-05-09 12:00:00');
    Carbon::setTestNow($today);

    ResourceAvailability::query()->create([
      'resource_id'   => $resource->id,
      'day_of_week'   => $today->dayOfWeek,
      'start_time'    => '08:00:00',
      'end_time'      => '17:00:00',
      'slot_duration' => 60,
      'is_active'     => true
    ]);

    $result = $this->service->generate($resource, $today);

    // الساعة 8 صباحاً أصبحت ماضياً
    $pastSlot = collect($result)->where('start', '2026-05-09 08:00:00')->first();
    $this->assertFalse($pastSlot['available']);

    // الساعة 2 ظهراً لا تزال مستقبلاً
    $futureSlot = collect($result)->where('start', '2026-05-09 14:00:00')->first();
    $this->assertTrue($futureSlot['available']);

    Carbon::setTestNow();
  }

  /** @test */
  public function it_marks_full_slots_as_unavailable()
  {
    $resource = Resource::factory()->create(['capacity' => 1]);
    $date = Carbon::parse('2026-07-01');

    ResourceAvailability::query()->create([
      'resource_id'   => $resource->id,
      'day_of_week'   => $date->dayOfWeek,
      'start_time'    => '10:00:00',
      'end_time'      => '11:00:00',
      'slot_duration' => 60,
      'is_active'     => true
    ]);

    Booking::factory()->create([
      'resource_id' => $resource->id,
      'start_at'    => '2026-07-01 10:00:00',
      'status'      => 'pending'
    ]);

    $result = $this->service->generate($resource, $date);

    $this->assertFalse($result[0]['available']);
  }
}
