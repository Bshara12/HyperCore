<?php

namespace Tests\Integration\Domains\Booking\Requests;

use App\Domains\Booking\Requests\RescheduleBookingRequest;
use App\Models\Booking;
use Illuminate\Support\Facades\Validator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RescheduleBookingRequestTest extends TestCase
{
  use RefreshDatabase;

  private array $rules;

  protected function setUp(): void
  {
    parent::setUp();
    $this->rules = (new RescheduleBookingRequest())->rules();
  }

  /** @test */
  public function it_fails_if_required_fields_are_missing()
  {
    $validator = Validator::make([], $this->rules);

    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->has('booking_id'))->toBeTrue();
    expect($validator->errors()->has('start_at'))->toBeTrue();
    expect($validator->errors()->has('end_at'))->toBeTrue();
  }

  /** @test */
  public function it_fails_if_booking_id_does_not_exist()
  {
    $data = [
      'booking_id' => 999,
      'start_at' => '2026-06-01 10:00:00',
      'end_at' => '2026-06-01 11:00:00',
    ];

    $validator = Validator::make($data, $this->rules);

    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->has('booking_id'))->toBeTrue();
  }

  /** @test */
  public function it_fails_if_end_at_is_before_start_at()
  {
    $booking = Booking::factory()->create();

    $data = [
      'booking_id' => $booking->id,
      'start_at' => '2026-06-01 10:00:00',
      'end_at' => '2026-06-01 09:00:00', // توقيت خاطئ
    ];

    $validator = Validator::make($data, $this->rules);

    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->has('end_at'))->toBeTrue();
  }

  /** @test */
  public function it_passes_with_valid_reschedule_data()
  {
    $booking = Booking::factory()->create();

    $data = [
      'booking_id' => $booking->id,
      'start_at' => '2026-06-10 14:00:00',
      'end_at' => '2026-06-10 15:00:00',
    ];

    $validator = Validator::make($data, $this->rules);

    expect($validator->fails())->toBeFalse();
  }
}
