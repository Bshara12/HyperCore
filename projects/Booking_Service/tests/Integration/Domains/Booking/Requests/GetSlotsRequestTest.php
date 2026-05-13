<?php

namespace Tests\Unit\Domains\Booking\Requests;

use App\Domains\Booking\Requests\GetSlotsRequest;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class GetSlotsRequestTest extends TestCase
{
  private array $rules;
  private array $messages;

  protected function setUp(): void
  {
    parent::setUp();
    $request = new GetSlotsRequest();
    $this->rules = $request->rules();
    $this->messages = $request->messages();
  }

  /** @test */
  public function it_fails_if_date_is_missing()
  {
    $validator = Validator::make([], $this->rules, $this->messages);

    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->first('date'))->toBe('Date is required.');
  }

  /** @test */
  public function it_fails_if_date_format_is_invalid()
  {
    // تجربة تنسيق خاطئ (D/M/Y)
    $data = ['date' => '15-01-2025'];
    $validator = Validator::make($data, $this->rules, $this->messages);

    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->first('date'))->toBe('Date must be in Y-m-d format. Example: 2025-01-15');
  }

  /** @test */
  public function it_fails_if_value_is_not_a_date()
  {
    $data = ['date' => 'not-a-date'];
    $validator = Validator::make($data, $this->rules, $this->messages);

    expect($validator->fails())->toBeTrue();
  }

  /** @test */
  public function it_passes_if_date_is_valid_and_formatted_correctly()
  {
    $data = ['date' => '2026-05-09'];
    $validator = Validator::make($data, $this->rules, $this->messages);

    expect($validator->fails())->toBeFalse();
  }
}
