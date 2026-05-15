<?php

namespace Tests\Feature\Domains\E_Commerce\Requests;

use App\Domains\E_Commerce\Requests\UpdateOfferRequest;
use Illuminate\Support\Facades\Validator;

beforeEach(function () {
  $this->rules = (new UpdateOfferRequest())->rules();
});

it('covers all validation logic paths', function ($type, $config, $shouldPass, $expectedErrorField = null) {
  $request = new UpdateOfferRequest();
  $data = [
    'benefit_type' => $type,
    'benefit_config' => $config
  ];

  $request->merge($data);
  $validator = Validator::make($data, $this->rules);
  $request->withValidator($validator);

  expect($validator->passes())->toBe($shouldPass);

  if ($expectedErrorField) {
    // فحص وجود الخطأ في الـ Error Bag
    expect($validator->errors()->has($expectedErrorField))
      ->toBeTrue("Expected error field [{$expectedErrorField}] was not found in: " . implode(', ', $validator->errors()->keys()));
  }
  // ... داخل التابع it('covers all validation logic paths')

})->with([
  'no type provided' => [null, null, true],

  // 2. تغطية سطر 51: نرسل config موجودة لكنها لا تحتوي على percentage
  'percentage missing field' => [
    'percentage',
    ['other_key' => 'ignore'], // مصفوفة غير فارغة لتجاوز شرط if (!$config)
    false,
    'benefit_config.percentage'
  ],

  // 3. تغطية سطر 57: نرسل config موجودة لكنها لا تحتوي على fixed_amount
  'fixed_amount missing field' => [
    'fixed_amount',
    ['other_key' => 'ignore'],
    false,
    'benefit_config.fixed_amount'
  ],

  'quantity missing discount_type' => [
    'quantity',
    ['quantity' => 5, 'discount_value' => 10],
    false,
    'benefit_config.discount_type'
  ],

  'buy_x_get_y missing acquired_item' => [
    'buy_x_get_y',
    ['targeted_item' => 1, 'targeted_item_count' => 2, 'acquired_item_count' => 1],
    false,
    'benefit_config.acquired_item'
  ],

  'total_price invalid discount_type' => [
    'total_price',
    ['total_price' => 100, 'discount_type' => 'invalid', 'discount_value' => 10],
    false,
    'benefit_config.discount_type'
  ],

  // 7. تغطية سطر 42: عندما يكون الـ config مفقوداً تماماً (null)
  'type exists but config is null' => [
    'percentage',
    null,
    false,
    'benefit_config'
  ],

  // ... أضف هذه الحالات داخل الـ with()

  // 1. تغطية الأسطر 76-81: نوع خصم خاطئ في الـ quantity
  'quantity with invalid discount_type' => [
    'quantity',
    [
      'quantity' => 5,
      'discount_type' => 'invalid_type', // هذا سيفعل الشرط في سطر 78
      'discount_value' => 10
    ],
    false,
    'benefit_config.discount_type'
  ],

  // 2. تغطية الأسطر 92-97: نوع خصم خاطئ في الـ total_price
  'total_price with invalid discount_type' => [
    'total_price',
    [
      'total_price' => 1000,
      'discount_type' => 'not_supported', // هذا سيفعل الشرط في سطر 94
      'discount_value' => 100
    ],
    false,
    'benefit_config.discount_type'
  ],

  // 3. لضمان تغطية الـ foreach في سطر 85 بالكامل (حالة النجاح)
  'total_price valid full config' => [
    'total_price',
    [
      'total_price' => 500,
      'discount_type' => 'fixed_amount',
      'discount_value' => 50
    ],
    true
  ],

  'total_price missing discount_value' => [
    'total_price',
    [
      'total_price' => 1000,
      'discount_type' => 'percentage'
      // حذفنا discount_value هنا لتفعيل السطر 87
    ],
    false,
    'benefit_config.discount_value'
  ],
]);
