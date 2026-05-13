<?php

namespace Tests\Integration\Domains\Booking\Requests;

use App\Domains\Booking\Requests\CancelBookingRequest;
use App\Models\Booking;
use Illuminate\Support\Facades\Validator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CancelBookingRequestTest extends TestCase
{
  use RefreshDatabase;

  private array $rules;

  protected function setUp(): void
  {
    parent::setUp();
    // جلب القواعد من الـ Request مباشرة لضمان مطابقة الاختبار للكود الفعلي
    $this->rules = (new CancelBookingRequest())->rules();
  }

  /** @test */
  public function it_fails_if_booking_id_is_missing()
  {
    $data = [];
    $validator = Validator::make($data, $this->rules);

    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->has('booking_id'))->toBeTrue();
  }

  /** @test */
  public function it_fails_if_booking_id_does_not_exist_in_database()
  {
    $data = ['booking_id' => 999]; // رقم غير موجود
    $validator = Validator::make($data, $this->rules);

    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->has('booking_id'))->toBeTrue();
  }

  /** @test */
  public function it_passes_if_booking_id_exists_in_database()
  {
    // إنشاء حجز في قاعدة البيانات باستخدام الـ Factory
    $booking = Booking::factory()->create();

    $data = ['booking_id' => $booking->id];
    $validator = Validator::make($data, $this->rules);

    expect($validator->fails())->toBeFalse();
  }
}
