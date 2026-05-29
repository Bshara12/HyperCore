<?php

use App\Domains\CMS\DTOs\Project\UpdateProjectDTO;
use App\Domains\CMS\Requests\UpdateProjectRequest;

test('it maps request data to DTO properties correctly', function () {
  $request = new UpdateProjectRequest();
  $request->merge([
    'name' => 'New Project Name',
    'supported_languages' => ['en', 'ar'],
    'enabled_modules' => ['blog', 'shop'],
  ]);

  $dto = UpdateProjectDTO::fromRequest($request);

  expect($dto->name)->toBe('New Project Name')
    ->and($dto->supportedLanguages)->toBe(['en', 'ar'])
    ->and($dto->enabledModules)->toBe(['blog', 'shop']);
});

test('it filters out null values in toArray method', function () {
  // نقوم بإنشاء DTO مع بعض القيم الفارغة (null)
  $dto = new UpdateProjectDTO(
    name: 'My Project',
    supportedLanguages: null, // هذه يجب أن تُحذف
    enabledModules: ['cms']
  );

  $array = $dto->toArray();

  // التأكد من أن المصفوفة تحتوي على القيم الموجودة فقط
  expect($array)->toBe([
    'name' => 'My Project',
    'enabled_modules' => ['cms'],
  ])
    // التأكد من أن supported_languages غير موجودة
    ->and($array)->not->toHaveKey('supported_languages');
});
