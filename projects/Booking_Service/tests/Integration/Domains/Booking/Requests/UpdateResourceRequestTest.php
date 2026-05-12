<?php

namespace Tests\Unit\Domains\Booking\Requests;

use App\Domains\Booking\Requests\UpdateResourceRequest;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class UpdateResourceRequestTest extends TestCase
{
  private array $rules;

  protected function setUp(): void
  {
    parent::setUp();
    $this->rules = (new UpdateResourceRequest())->rules();
  }

  /** @test */
  public function it_passes_when_no_fields_are_provided()
  {
    // بما أن كل الحقول "sometimes"، فالمصفوفة الفارغة يجب أن تمر
    $validator = Validator::make([], $this->rules);

    expect($validator->fails())->toBeFalse();
  }

  /** @test */
  public function it_fails_if_status_is_invalid()
  {
    $data = ['status' => 'archived']; // قيمة غير موجودة في active, inactive
    $validator = Validator::make($data, $this->rules);

    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->has('status'))->toBeTrue();
  }

  /** @test */
  public function it_fails_if_capacity_is_less_than_one()
  {
    $data = ['capacity' => 0];
    $validator = Validator::make($data, $this->rules);

    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->has('capacity'))->toBeTrue();
  }

  /** @test */
  public function it_fails_if_price_is_less_than_minimum()
  {
    $data = ['price' => 0.001]; // أقل من 0.01
    $validator = Validator::make($data, $this->rules);

    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->has('price'))->toBeTrue();
  }

  /** @test */
  public function it_passes_if_price_is_null_and_provided()
  {
    // القاعدة تقول nullable، لذا يجب أن يمر إذا أرسلنا null لتصفير السعر
    $data = ['price' => null];
    $validator = Validator::make($data, $this->rules);

    expect($validator->fails())->toBeFalse();
  }

  /** @test */
  public function it_passes_with_partial_valid_data()
  {
    $data = [
      'name' => 'Updated Room Name',
      'status' => 'inactive'
    ];

    $validator = Validator::make($data, $this->rules);

    expect($validator->fails())->toBeFalse();
  }

  /** @test */
  public function it_passes_with_all_valid_data()
  {
    $data = [
      'name' => 'New Suite',
      'type' => 'vip',
      'capacity' => 10,
      'status' => 'active',
      'payment_type' => 'paid',
      'price' => 500.00,
      'settings' => ['cleaning_fee' => true]
    ];

    $validator = Validator::make($data, $this->rules);

    expect($validator->fails())->toBeFalse();
  }
}
