<?php

namespace Tests\Unit\Domains\Booking\Requests;

use App\Domains\Booking\Requests\SetCancellationPolicyRequest;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class SetCancellationPolicyRequestTest extends TestCase
{
  private array $rules;

  protected function setUp(): void
  {
    parent::setUp();
    $this->rules = (new SetCancellationPolicyRequest())->rules();
  }

  /** @test */
  public function it_fails_if_policies_is_missing_or_not_an_array()
  {
    $validator = Validator::make(['policies' => 'not-an-array'], $this->rules);

    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->has('policies'))->toBeTrue();
  }

  /** @test */
  public function it_fails_if_policies_array_is_empty()
  {
    $validator = Validator::make(['policies' => []], $this->rules);

    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->has('policies'))->toBeTrue();
  }

  /** @test */
  public function it_fails_if_hours_before_is_negative()
  {
    $data = [
      'policies' => [
        ['hours_before' => -5, 'refund_percentage' => 50]
      ]
    ];

    $validator = Validator::make($data, $this->rules);

    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->has('policies.0.hours_before'))->toBeTrue();
  }

  /** @test */
  public function it_fails_if_refund_percentage_is_out_of_range()
  {
    $data = [
      'policies' => [
        ['hours_before' => 24, 'refund_percentage' => 110] // أكبر من 100
      ]
    ];

    $validator = Validator::make($data, $this->rules);

    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->has('policies.0.refund_percentage'))->toBeTrue();
  }

  /** @test */
  public function it_fails_if_required_fields_within_array_are_missing()
  {
    $data = [
      'policies' => [
        ['description' => 'Only description'] // نقص الساعات والنسبة
      ]
    ];

    $validator = Validator::make($data, $this->rules);

    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->has('policies.0.hours_before'))->toBeTrue();
    expect($validator->errors()->has('policies.0.refund_percentage'))->toBeTrue();
  }

  /** @test */
  public function it_passes_with_multiple_valid_policies()
  {
    $data = [
      'policies' => [
        [
          'hours_before' => 48,
          'refund_percentage' => 100,
          'description' => 'Full refund'
        ],
        [
          'hours_before' => 24,
          'refund_percentage' => 50,
          'description' => 'Partial refund'
        ],
        [
          'hours_before' => 0,
          'refund_percentage' => 0,
          'description' => 'No refund'
        ]
      ]
    ];

    $validator = Validator::make($data, $this->rules);

    expect($validator->fails())->toBeFalse();
  }
}
