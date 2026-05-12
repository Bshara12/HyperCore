<?php

namespace Tests\Integration\Domains\Booking\Requests;

use App\Domains\Booking\Requests\CreateBookingRequest;
use App\Models\Resource;
use Illuminate\Support\Facades\Validator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreateBookingRequestTest extends TestCase
{
  use RefreshDatabase;

  private array $rules;

  protected function setUp(): void
  {
    parent::setUp();
    $this->rules = (new CreateBookingRequest())->rules();
  }

  /** @test */
  public function it_fails_if_required_fields_are_missing()
  {
    $validator = Validator::make([], $this->rules);

    expect($validator->fails())->toBeTrue();
    // نتحقق من الحقول الأساسية على الأقل
    expect($validator->errors()->has('resource_id'))->toBeTrue();
    expect($validator->errors()->has('start_at'))->toBeTrue();
    expect($validator->errors()->has('amount'))->toBeTrue();
  }

  /** @test */
  public function it_fails_if_resource_id_does_not_exist()
  {
    $data = $this->getValidData(['resource_id' => 999]);
    $validator = Validator::make($data, $this->rules);

    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->has('resource_id'))->toBeTrue();
  }

  /** @test */
  public function it_fails_if_end_at_is_before_or_equal_to_start_at()
  {
    $data = $this->getValidData([
      'start_at' => '2026-06-01 10:00:00',
      'end_at'   => '2026-06-01 09:00:00', // قبل البداية
    ]);

    $validator = Validator::make($data, $this->rules);
    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->has('end_at'))->toBeTrue();
  }

  /** @test */
  public function it_fails_if_amount_is_negative()
  {
    $data = $this->getValidData(['amount' => -10]);
    $validator = Validator::make($data, $this->rules);

    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->has('amount'))->toBeTrue();
  }

  /** @test */
  public function it_passes_with_valid_data()
  {
    $resource = Resource::factory()->create();

    $data = [
      'resource_id' => $resource->id,
      'start_at'    => '2026-06-01 10:00:00',
      'end_at'      => '2026-06-01 11:00:00',
      'amount'      => 150.50,
      'currency'    => 'USD',
      'gateway'     => 'stripe',
      'token'       => 'tok_visa',
    ];

    $validator = Validator::make($data, $this->rules);

    expect($validator->fails())->toBeFalse();
  }

  /**
   * دالة مساعدة لتوفير بيانات صالحة افتراضياً وتعديل ما يلزم
   */
  private function getValidData(array $overrides = []): array
  {
    return array_merge([
      'resource_id' => 1,
      'start_at'    => '2026-06-01 10:00:00',
      'end_at'      => '2026-06-01 11:00:00',
      'amount'      => 100,
      'currency'    => 'USD',
      'gateway'     => 'stripe',
      'token'       => 'token_123',
    ], $overrides);
  }
}
