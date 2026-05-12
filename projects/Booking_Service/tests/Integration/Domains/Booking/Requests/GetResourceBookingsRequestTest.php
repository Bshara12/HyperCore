<?php

namespace Tests\Unit\Domains\Booking\Requests;

use App\Domains\Booking\Requests\GetResourceBookingsRequest;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class GetResourceBookingsRequestTest extends TestCase
{
  private array $rules;

  protected function setUp(): void
  {
    parent::setUp();
    $this->rules = (new GetResourceBookingsRequest())->rules();
  }

  /** @test */
  public function it_passes_with_no_parameters()
  {
    // بما أن جميع الحقول nullable، يجب أن يمر الاختبار بدون بيانات
    $validator = Validator::make([], $this->rules);

    expect($validator->fails())->toBeFalse();
  }

  /** @test */
  public function it_fails_if_status_is_not_in_allowed_list()
  {
    $data = ['status' => 'invalid_status'];
    $validator = Validator::make($data, $this->rules);

    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->has('status'))->toBeTrue();
  }

  /** @test */
  public function it_passes_with_all_valid_statuses()
  {
    $statuses = ['pending', 'confirmed', 'cancelled', 'completed', 'no_show'];

    foreach ($statuses as $status) {
      $validator = Validator::make(['status' => $status], $this->rules);
      expect($validator->fails())->toBeFalse();
    }
  }

  /** @test */
  public function it_fails_if_date_format_is_incorrect()
  {
    // التنسيق المطلوب Y-m-d
    $data = ['from' => '10-05-2026'];
    $validator = Validator::make($data, $this->rules);

    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->has('from'))->toBeTrue();
  }

  /** @test */
  public function it_fails_if_to_date_is_before_from_date()
  {
    $data = [
      'from' => '2026-05-10',
      'to'   => '2026-05-09', // قبل تاريخ البداية
    ];
    $validator = Validator::make($data, $this->rules);

    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->has('to'))->toBeTrue();
  }

  /** @test */
  public function it_passes_if_to_date_is_equal_to_from_date()
  {
    $data = [
      'from' => '2026-05-10',
      'to'   => '2026-05-10',
    ];
    $validator = Validator::make($data, $this->rules);

    expect($validator->fails())->toBeFalse();
  }

  /** @test */
  public function it_passes_with_full_valid_data()
  {
    $data = [
      'status' => 'confirmed',
      'from'   => '2026-01-01',
      'to'     => '2026-12-31',
    ];
    $validator = Validator::make($data, $this->rules);

    expect($validator->fails())->toBeFalse();
  }
}
