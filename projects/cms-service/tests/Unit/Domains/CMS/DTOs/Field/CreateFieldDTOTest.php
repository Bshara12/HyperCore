<?php

use App\Domains\CMS\DTOs\Field\CreateFieldDTO;
use App\Domains\CMS\Requests\CreateFieldRequest;
use App\Models\DataTypeField;

test('it creates DTO from request correctly', function () {
  $request = new CreateFieldRequest();
  $request->merge([
    'name' => 'Field Name',
    'type' => 'text',
    'required' => '1',
    'translatable' => '0',
    'validation_rules' => ['required'],
    'settings' => ['min' => 5],
    'sort_order' => 10,
  ]);

  $dto = CreateFieldDTO::fromRequest($request, 1);

  expect($dto->data_type_id)->toBe(1)
    ->and($dto->name)->toBe('Field Name')
    ->and($dto->required)->toBeTrue()
    ->and($dto->translatable)->toBeFalse()
    ->and($dto->validation_rules)->toBe(['required'])
    ->and($dto->settings)->toBe(['min' => 5])
    ->and($dto->sort_order)->toBe(10);
});

test('it creates DTO for update using existing field values as defaults', function () {
  // محاكاة المودل الحالي
  $field = new DataTypeField([
    'data_type_id' => 1,
    'type' => 'text',
    'required' => true,
    'translatable' => false,
    'validation_rules' => ['numeric'],
    'settings' => ['max' => 10],
    'sort_order' => 5,
  ]);

  // محاكاة طلب تحديث (يغير الاسم فقط، ويترك الباقي)
  $request = new CreateFieldRequest();
  $request->merge([
    'name' => 'New Name',
    // باقي الحقول مفقودة هنا، يجب أن يأخذها من الـ $field
  ]);

  $dto = CreateFieldDTO::fromRequestForUpdate($request, $field);

  expect($dto->name)->toBe('New Name')
    ->and($dto->type)->toBe('text') // المأخوذ من المودل
    ->and($dto->required)->toBeTrue() // المأخوذ من المودل
    ->and($dto->validation_rules)->toBe(['numeric']); // المأخوذ من المودل
});
