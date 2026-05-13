<?php

namespace Tests\Unit\Domains\Booking\Requests;

use App\Domains\Booking\Requests\CreateResourceRequest;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class CreateResourceRequestTest extends TestCase
{
  private array $baseData;

  protected function setUp(): void
  {
    parent::setUp();
    // بيانات أساسية صالحة لاستخدامها في الاختبارات
    $this->baseData = [
      'data_entry_id' => 1,
      'name' => 'Meeting Room',
      'type' => 'room',
      'payment_type' => 'free',
    ];
  }

  /** @test */
  public function it_fails_if_required_fields_are_missing()
  {
    $validator = Validator::make([], (new CreateResourceRequest())->rules());

    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->has('data_entry_id'))->toBeTrue();
    expect($validator->errors()->has('name'))->toBeTrue();
    expect($validator->errors()->has('payment_type'))->toBeTrue();
  }

  /** @test */
  public function it_fails_if_payment_type_is_invalid()
  {
    $data = array_merge($this->baseData, ['payment_type' => 'subscription']);
    $validator = Validator::make($data, (new CreateResourceRequest())->rules());

    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->has('payment_type'))->toBeTrue();
  }

  /** @test */
  public function it_fails_if_price_is_missing_when_payment_type_is_paid()
  {
    $data = array_merge($this->baseData, [
      'payment_type' => 'paid',
      'price' => null
    ]);

    $request = new CreateResourceRequest();
    // يجب تمرير البيانات للـ Request ليتمكن من قراءة input('payment_type')
    $request->merge($data);

    $validator = Validator::make($data, $request->rules(), $request->messages());

    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->first('price'))->toBe('Price is required when payment type is paid.');
  }

  /** @test */
  public function it_fails_if_price_is_less_than_minimum()
  {
    $data = array_merge($this->baseData, [
      'payment_type' => 'paid',
      'price' => 0
    ]);

    $request = new CreateResourceRequest();
    $request->merge($data);

    $validator = Validator::make($data, $request->rules(), $request->messages());

    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->first('price'))->toBe('Price must be greater than zero.');
  }

  /** @test */
  public function it_passes_if_price_is_missing_when_payment_type_is_free()
  {
    $data = array_merge($this->baseData, [
      'payment_type' => 'free',
      'price' => null
    ]);

    $request = new CreateResourceRequest();
    $request->merge($data);

    $validator = Validator::make($data, $request->rules());

    expect($validator->fails())->toBeFalse();
  }

  /** @test */
  public function it_passes_with_full_valid_data()
  {
    $data = [
      'data_entry_id' => 10,
      'name' => 'VIP Lounge',
      'type' => 'space',
      'capacity' => 5,
      'payment_type' => 'paid',
      'price' => 99.99,
      'settings' => ['wifi' => true, 'projector' => false]
    ];

    $request = new CreateResourceRequest();
    $request->merge($data);

    $validator = Validator::make($data, $request->rules());

    expect($validator->fails())->toBeFalse();
  }
}
