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

// القيم الفارغة تصل كـ null بفعل ConvertEmptyStringsToNull، فالحقل الاختياري
// الفارغ يجب أن يُتخطّى، والإلزامي الفارغ يجب أن يفشل بـ "is required".

test('it skips validation when optional field is present but blank', function () {
  $repoMock = Mockery::mock(FieldRepositoryInterface::class);
  $resolverMock = Mockery::mock(FieldValidatorResolver::class);

  $field = (object) ['required' => false, 'type' => 'string', 'name' => 'Meta Title'];

  $repoMock->shouldReceive('getByDataType')
    ->andReturn(['meta_title' => $field]);

  // لا يجب أن يصل أي validator إلى قيمة فارغة
  $resolverMock->shouldNotReceive('resolve');

  $action = new ValidateFieldsAction($repoMock, $resolverMock);

  $action->execute(1, ['meta_title' => ['en' => null]]);
  $action->execute(1, ['meta_title' => ['en' => '']]);
  $action->execute(1, ['meta_title' => ['en' => null, 'ar' => '']]);

  expect(true)->toBeTrue();
});

test('it reports a blank required field as required, not as a type error', function () {
  $repoMock = Mockery::mock(FieldRepositoryInterface::class);
  $resolverMock = Mockery::mock(FieldValidatorResolver::class);

  $field = (object) ['required' => true, 'type' => 'string', 'name' => 'Title'];

  $repoMock->shouldReceive('getByDataType')
    ->andReturn(['title' => $field]);

  $resolverMock->shouldNotReceive('resolve');

  $action = new ValidateFieldsAction($repoMock, $resolverMock);

  expect(fn() => $action->execute(1, ['title' => ['en' => null]]))
    ->toThrow(DomainException::class, 'Field title is required.');

  expect(fn() => $action->execute(1, ['title' => ['en' => '']]))
    ->toThrow(DomainException::class, 'Field title is required.');
});

test('it accepts a required field filled in only some languages', function () {
  $repoMock = Mockery::mock(FieldRepositoryInterface::class);
  $resolverMock = Mockery::mock(FieldValidatorResolver::class);
  $validatorMock = Mockery::mock(FieldValidator::class);

  $field = (object) ['required' => true, 'type' => 'string', 'name' => 'Title'];

  $repoMock->shouldReceive('getByDataType')
    ->andReturn(['title' => $field]);

  $resolverMock->shouldReceive('resolve')->once()->with('string')->andReturn($validatorMock);
  $validatorMock->shouldReceive('validate')->once()->with('hello', (array) $field);

  $action = new ValidateFieldsAction($repoMock, $resolverMock);

  $action->execute(1, ['title' => ['en' => 'hello', 'ar' => null]]);

  expect(true)->toBeTrue();
});

test('it treats "0" as a real value on a required field', function () {
  $repoMock = Mockery::mock(FieldRepositoryInterface::class);
  $resolverMock = Mockery::mock(FieldValidatorResolver::class);
  $validatorMock = Mockery::mock(FieldValidator::class);

  $field = (object) ['required' => true, 'type' => 'number', 'name' => 'Stock'];

  $repoMock->shouldReceive('getByDataType')
    ->andReturn(['stock' => $field]);

  $resolverMock->shouldReceive('resolve')->once()->with('number')->andReturn($validatorMock);
  $validatorMock->shouldReceive('validate')->once()->with('0', (array) $field);

  $action = new ValidateFieldsAction($repoMock, $resolverMock);

  $action->execute(1, ['stock' => ['en' => '0']]);

  expect(true)->toBeTrue();
});

test('it does not enforce required on patch requests', function () {
  $repoMock = Mockery::mock(FieldRepositoryInterface::class);
  $resolverMock = Mockery::mock(FieldValidatorResolver::class);

  $field = (object) ['required' => true, 'type' => 'string', 'name' => 'Title'];

  $repoMock->shouldReceive('getByDataType')
    ->andReturn(['title' => $field]);

  $resolverMock->shouldNotReceive('resolve');

  $action = new ValidateFieldsAction($repoMock, $resolverMock);

  $action->execute(1, ['title' => ['en' => null]], false);

  expect(true)->toBeTrue();
});
