<?php

use App\Domains\AI\DTOs\ProvisionProjectFromSchemaDTO;

test('it maps request data to DTO properties correctly', function () {
  $data = [
    'project_info' => [
      'name' => 'AI Generator',
      'slug' => 'ai-gen',
      'languages' => ['en', 'ar'],
      'modules' => ['cms', 'crm'],
    ],
    'custom_data_types' => [
      ['name' => 'Article', 'fields' => []],
    ],
    'relations' => [
      ['from' => 'Article', 'to' => 'Category'],
    ],
  ];

  $dto = ProvisionProjectFromSchemaDTO::fromRequest($data, 10);

  expect($dto->ownerId)->toBe(10)
    ->and($dto->projectInfo)->toBe($data['project_info'])
    ->and($dto->customDataTypes)->toBe($data['custom_data_types'])
    ->and($dto->relations)->toBe($data['relations']);
});

test('it handles missing optional arrays by defaulting to empty arrays', function () {
  $data = [
    'project_info' => ['name' => 'Minimal Project'],
  ];

  $dto = ProvisionProjectFromSchemaDTO::fromRequest($data, 5);

  expect($dto->customDataTypes)->toBeEmpty()
    ->and($dto->relations)->toBeEmpty();
});
