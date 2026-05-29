<?php

namespace Tests\Unit\Domains\CMS\Actions\data;

use App\Domains\CMS\Actions\data\ValidateFieldsAction;
use App\Domains\CMS\Repositories\Interface\FieldRepositoryInterface;
use App\Domains\CMS\StrategyCheck\FieldValidatorResolver;
use App\Domains\CMS\StrategyCheck\FieldValidator; // تأكد من استيراد الواجهة
use DomainException;
use Mockery;

afterEach(function () {
  Mockery::close();
});

test('it throws exception when required field is missing', function () {
  // 1. تجهيز الـ Mocks
  $repoMock = Mockery::mock(FieldRepositoryInterface::class);
  $resolverMock = Mockery::mock(FieldValidatorResolver::class);

  // الحقل المطلوب موجود في الـ repo ولكن غير موجود في الـ values
  $field = (object) ['required' => true, 'type' => 'string'];
  $repoMock->shouldReceive('getByDataType')
    ->once()
    ->with(1)
    ->andReturn(['test-slug' => $field]);

  $action = new ValidateFieldsAction($repoMock, $resolverMock);

  // 2. التنفيذ والتأكد من رمي الاستثناء
  expect(fn() => $action->execute(1, []))
    ->toThrow(DomainException::class, 'Field test-slug is required.');
});

test('it validates fields when present', function () {
  // 1. تجهيز الـ Mocks
  $repoMock = Mockery::mock(FieldRepositoryInterface::class);
  $resolverMock = Mockery::mock(FieldValidatorResolver::class);
  $validatorMock = Mockery::mock(FieldValidator::class);

  $field = (object) ['required' => true, 'type' => 'string', 'name' => 'Test Field'];

  $repoMock->shouldReceive('getByDataType')
    ->once()
    ->with(1)
    ->andReturn(['test-slug' => $field]);

  // يجب أن يتم حل الـ Validator واستدعاء validate
  $resolverMock->shouldReceive('resolve')
    ->once()
    ->with('string')
    ->andReturn($validatorMock);

  $validatorMock->shouldReceive('validate')
    ->once()
    ->with('hello', (array) $field);

  $action = new ValidateFieldsAction($repoMock, $resolverMock);

  // 2. التنفيذ
  $values = ['test-slug' => ['en' => 'hello']];
  $action->execute(1, $values);

  // إذا وصلنا هنا بدون استثناء، الاختبار ناجح
  expect(true)->toBeTrue();
});

test('it skips validation when optional field is missing', function () {
  $repoMock = Mockery::mock(FieldRepositoryInterface::class);
  $resolverMock = Mockery::mock(FieldValidatorResolver::class);

  // الحقل غير مطلوب
  $field = (object) ['required' => false, 'type' => 'string'];

  $repoMock->shouldReceive('getByDataType')
    ->once()
    ->with(1)
    ->andReturn(['optional-slug' => $field]);

  // لا نتوقع استدعاء الـ resolver
  $resolverMock->shouldNotReceive('resolve');

  $action = new ValidateFieldsAction($repoMock, $resolverMock);

  $action->execute(1, []);

  expect(true)->toBeTrue();
});
