<?php

namespace Tests\Unit\Domains\Booking\Requests;

use App\Domains\Booking\Requests\SetAvailabilityRequest;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class SetAvailabilityRequestTest extends TestCase
{
  private array $rules;
  private array $messages;

  protected function setUp(): void
  {
    parent::setUp();
    $request = new SetAvailabilityRequest();
    $this->rules = $request->rules();
    $this->messages = $request->messages();
  }

  /** @test */
  public function it_fails_if_availabilities_is_missing_or_empty()
  {
    $validator = Validator::make(['availabilities' => []], $this->rules);

    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->has('availabilities'))->toBeTrue();
  }

  /** @test */
  public function it_fails_if_day_of_week_is_out_of_range()
  {
    $data = [
      'availabilities' => [
        ['day_of_week' => 7, 'start_time' => '09:00', 'end_time' => '17:00', 'slot_duration' => 30]
      ]
    ];

    $validator = Validator::make($data, $this->rules, $this->messages);

    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->first('availabilities.0.day_of_week'))
      ->toBe('Day of week must be between 0 (Sunday) and 6 (Saturday).');
  }

  /** @test */
  public function it_fails_if_end_time_is_not_after_start_time()
  {
    $data = [
      'availabilities' => [
        ['day_of_week' => 1, 'start_time' => '10:00', 'end_time' => '09:00', 'slot_duration' => 30]
      ]
    ];

    $validator = Validator::make($data, $this->rules, $this->messages);

    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->first('availabilities.0.end_time'))
      ->toBe('End time must be after start time.');
  }

  /** @test */
  public function it_fails_if_slot_duration_is_less_than_minimum()
  {
    $data = [
      'availabilities' => [
        ['day_of_week' => 2, 'start_time' => '08:00', 'end_time' => '12:00', 'slot_duration' => 4]
      ]
    ];

    $validator = Validator::make($data, $this->rules, $this->messages);

    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->first('availabilities.0.slot_duration'))
      ->toBe('Slot duration must be at least 5 minutes.');
  }

  /** @test */
  public function it_fails_if_time_format_is_incorrect()
  {
    $data = [
      'availabilities' => [
        ['day_of_week' => 3, 'start_time' => '9 AM', 'end_time' => '5 PM', 'slot_duration' => 60]
      ]
    ];

    $validator = Validator::make($data, $this->rules);

    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->has('availabilities.0.start_time'))->toBeTrue();
  }

  /** @test */
  public function it_passes_with_multiple_valid_availability_objects()
  {
    $data = [
      'availabilities' => [
        [
          'day_of_week' => 0, // الأحد
          'start_time' => '08:00',
          'end_time' => '14:00',
          'slot_duration' => 60,
          'is_active' => true
        ],
        [
          'day_of_week' => 1, // الاثنين
          'start_time' => '10:00',
          'end_time' => '18:00',
          'slot_duration' => 30,
          'is_active' => false
        ]
      ]
    ];

    $validator = Validator::make($data, $this->rules);

    expect($validator->fails())->toBeFalse();
  }
}
