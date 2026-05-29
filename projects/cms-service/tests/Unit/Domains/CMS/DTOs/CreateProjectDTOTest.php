<?php

use App\Domains\CMS\DTOs\CreateProjectDTO;
use App\Domains\CMS\Requests\CreateProjectRequest;
use Symfony\Component\HttpKernel\Exception\HttpException;

test('it creates DTO from request when auth_user is an object', function () {
  $request = new CreateProjectRequest();
  $request->merge([
    'name' => 'My New Project',
    'supported_languages' => ['ar', 'en'],
    'enabled_modules' => ['cms'],
  ]);

  // محاكاة المستخدم كـ Object
  $request->attributes->set('auth_user', (object)['id' => 123]);

  $dto = CreateProjectDTO::fromRequest($request);

  expect($dto->name)->toBe('My New Project')
    ->and($dto->ownerId)->toBe(123)
    ->and($dto->supportedLanguages)->toBe(['ar', 'en'])
    ->and($dto->enabledModules)->toBe(['cms']);
});

test('it creates DTO from request when auth_user is an array', function () {
  $request = new CreateProjectRequest();
  $request->merge(['name' => 'Array User Project']);

  // محاكاة المستخدم كـ Array
  $request->attributes->set('auth_user', ['id' => 456]);

  $dto = CreateProjectDTO::fromRequest($request);

  expect($dto->ownerId)->toBe(456);
});

test('it throws 401 unauthorized when auth_user is missing', function () {
  $request = new CreateProjectRequest();
  $request->attributes->set('auth_user', null);

  // نتوقع حدوث استثناء من نوع HttpException (وهو المسؤول عن 401)
  expect(fn() => CreateProjectDTO::fromRequest($request))
    ->toThrow(HttpException::class, 'Unauthorized');
});

test('it maps properties to array correctly', function () {
  $dto = new CreateProjectDTO('Name', 1, ['ar'], ['cms']);

  expect($dto->toArray())->toBe([
    'name' => 'Name',
    'owner_id' => 1,
    'supported_languages' => ['ar'],
    'enabled_modules' => ['cms'],
  ]);
});
