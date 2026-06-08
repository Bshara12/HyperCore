<?php

use App\Domains\CMS\Requests\AIGenerateSchemaRequest;
use Illuminate\Support\Facades\Validator;

test('it passes with valid data', function () {
  $data = [
    'project_info' => [
      'name' => 'My Project',
      'slug' => 'my-project',
      'languages' => ['ar', 'en'],
      'modules' => ['cms'],
    ],
    'custom_data_types' => [
      [
        'name' => 'Article',
        'fields' => [
          ['name' => 'title', 'type' => 'text', 'required' => true, 'translatable' => true]
        ]
      ]
    ]
  ];

  $validator = validateRequest($data, AIGenerateSchemaRequest::class);
  expect($validator->passes())->toBeTrue();
});

test('it fails if project_info is missing', function () {
  $validator = validateRequest([], AIGenerateSchemaRequest::class);
  expect($validator->fails())->toBeTrue();
  expect($validator->errors()->has('project_info'))->toBeTrue();
});

test('it validates the field name regex correctly', function ($fieldName, $shouldPass) {
  $data = [
    'project_info' => ['name' => 'P', 'languages' => ['en'], 'modules' => ['cms']],
    'custom_data_types' => [
      [
        'name' => 'Type',
        'fields' => [['name' => $fieldName, 'type' => 'text']]
      ]
    ]
  ];

  $validator = validateRequest($data, AIGenerateSchemaRequest::class);

  if ($shouldPass) {
    expect($validator->passes())->toBeTrue();
  } else {
    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->has('custom_data_types.0.fields.0.name'))->toBeTrue();
  }
})->with([
  ['valid_name', true],
  ['valid123', true],
  ['my_field', true],
  ['InvalidName', false], // يبدأ بحرف كبير
  ['123field', false],    // يبدأ برقم
  ['!field', false],      // يحتوي رمز
]);

test('it validates enum values for languages and modules', function ($key, $value, $shouldPass) {
  $data = [
    'project_info' => [
      'name' => 'Test',
      'languages' => ['en'],
      'modules' => ['cms'],
    ]
  ];

  $data['project_info'][$key] = [$value];

  $validator = validateRequest($data, AIGenerateSchemaRequest::class);

  if ($shouldPass) {
    expect($validator->passes())->toBeTrue();
  } else {
    expect($validator->fails())->toBeTrue();
  }
})->with([
  ['languages', 'jp', false], // لغة غير مدعومة
  ['modules', 'invalid_module', false], // موديول غير موجود
]);
