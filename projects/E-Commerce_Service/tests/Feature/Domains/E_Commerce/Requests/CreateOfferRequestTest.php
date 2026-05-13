<?php

namespace Tests\Feature\Domains\E_Commerce\Requests;

use App\Domains\E_Commerce\Requests\CreateOfferRequest;
use Illuminate\Support\Facades\Validator;

beforeEach(function () {
  $this->rules = (new CreateOfferRequest())->rules();
});

it('validates basic offer fields correctly', function ($data, $shouldPass) {
  $validator = Validator::make($data, $this->rules);

  expect($validator->passes())->toBe($shouldPass);
})->with([
  'valid manual offer' => [[
    'name' => 'Spring Sale',
    'type' => 'manual',
    'data_type_id' => 1,
    'is_code_offer' => false,
    'benefit_type' => 'percentage',
    'benefit_config' => ['percentage' => 10],
  ], true],
  'invalid type' => [[
    'name' => 'Spring Sale',
    'type' => 'invalid_type', // ليس manual أو dynamic
    'data_type_id' => 1,
    'is_code_offer' => false,
    'benefit_type' => 'percentage',
    'benefit_config' => ['percentage' => 10],
  ], false],
  'missing benefit_config' => [[
    'name' => 'Spring Sale',
    'type' => 'manual',
    'data_type_id' => 1,
    'is_code_offer' => false,
    'benefit_type' => 'percentage',
  ], false], // حقل إجباري
]);

it('validates conditions array structure when present', function ($conditions, $shouldPass) {
  $data = [
    'name' => 'Dynamic Offer',
    'type' => 'dynamic',
    'data_type_id' => 1,
    'is_code_offer' => false,
    'benefit_type' => 'fixed_amount',
    'benefit_config' => ['fixed_amount' => 50],
    'conditions' => $conditions
  ];

  $validator = Validator::make($data, $this->rules);
  expect($validator->passes())->toBe($shouldPass);
})->with([
  'valid conditions' => [[
    ['field' => 'price', 'operator' => '>', 'value' => '100']
  ], true],
  'missing operator in condition' => [[
    ['field' => 'price', 'value' => '100']
  ], false],
]);

it('validates date logic for offer duration', function ($start, $end, $shouldPass) {
  $data = [
    'name' => 'Timed Offer',
    'type' => 'manual',
    'data_type_id' => 1,
    'is_code_offer' => false,
    'benefit_type' => 'percentage',
    'benefit_config' => ['percentage' => 10],
    'start_at' => $start,
    'end_at' => $end
  ];

  $validator = Validator::make($data, $this->rules);
  expect($validator->passes())->toBe($shouldPass);
})->with([
  'valid dates'      => ['2026-05-01', '2026-06-01', true],
  'same day dates'   => ['2026-05-01', '2026-05-01', true],
  'invalid sequence' => ['2026-05-12', '2026-05-01', false], // النهاية قبل البداية
]);

it('requires offer_duration only if is_code_offer is true', function ($isCode, $duration, $shouldPass) {
  // مصفوفة بيانات كاملة لضمان عدم فشل الحقول الأخرى
  $data = [
    'name' => 'Test Offer',
    'type' => 'manual',
    'data_type_id' => 1,
    'benefit_type' => 'percentage',
    'benefit_config' => ['percentage' => 10],
    'is_code_offer' => $isCode,
  ];

  if ($duration !== null) {
    $data['offer_duration'] = $duration;
  }

  $validator = Validator::make($data, $this->rules);

  expect($validator->passes())->toBe($shouldPass);
})->with([
  'code offer with duration'    => [true, 24, true],    // الآن مع وجود البيانات الكاملة، true ستعمل
  'code offer missing duration' => [true, null, false],
  'regular offer no duration'   => [false, null, true],
]);
