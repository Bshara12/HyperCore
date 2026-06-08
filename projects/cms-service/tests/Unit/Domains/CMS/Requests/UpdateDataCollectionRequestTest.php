<?php

use App\Domains\CMS\Requests\UpdateDataCollectionRequest;
use App\Models\DataCollection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('it passes validation with valid inputs', function ($data) {
  $request = new UpdateDataCollectionRequest();
  $validator = Validator::make($data, $request->rules());

  expect($validator->passes())->toBeTrue();
})->with([
  'valid update' => [[
    'name' => 'New Name',
    'conditions' => [
      ['field' => 'price', 'operator' => '>', 'value' => 100]
    ],
    'conditions_logic' => 'or',
    'description' => 'Test desc'
  ]],
]);

test('it fails when manual collection has conditions', function () {
  // 1. إنشاء الكائن (تأكد أن القيمة 'manual' صحيحة وفقاً للـ Migration لديك)
  $collection = DataCollection::factory()->create([
    'slug' => 'my-collection',
    'type' => 'manual'
  ]);

  $request = new UpdateDataCollectionRequest();

  // 2. دمج البيانات في الـ Request ليعرفها `$this->has('conditions')`
  $data = ['conditions' => [['field' => 'x', 'operator' => '=', 'value' => 'y']]];
  $request->merge($data);

  // 3. محاكاة المسار
  $route = Mockery::mock(\Illuminate\Routing\Route::class);
  $route->shouldReceive('parameter')
    ->with('collectionSlug', null)
    ->andReturn('my-collection');
  $request->setRouteResolver(fn() => $route);

  // 4. تنفيذ الاختبار
  $validator = Validator::make($data, $request->rules());
  $request->withValidator($validator);

  expect($validator->fails())->toBeTrue()
    ->and($validator->errors()->has('conditions'))->toBeTrue()
    ->and($validator->errors()->first('conditions'))->toBe('You cannot send conditions for a manual collection.');
});

test('it allows conditions for non-manual collections', function () {
  // 4. استبدل 'automatic' بقيمة صحيحة يراها الـ Migration (مثلاً 'dynamic')
  $collection = DataCollection::factory()->create([
    'slug' => 'auto-collection',
    'type' => 'dynamic'
  ]);

  $request = new UpdateDataCollectionRequest();

  $route = Mockery::mock(\Illuminate\Routing\Route::class);
  $route->shouldReceive('parameter')
    ->with('collectionSlug', null)
    ->andReturn('auto-collection');

  $request->setRouteResolver(fn() => $route);

  $data = ['conditions' => [['field' => 'x', 'operator' => '=', 'value' => 'y']]];
  $validator = Validator::make($data, $request->rules());
  $request->withValidator($validator);

  expect($validator->passes())->toBeTrue();
});

test('it does nothing and passes when collection does not exist', function () {
  $request = new UpdateDataCollectionRequest();

  // 1. محاكاة مسار لـ slug غير موجود في قاعدة البيانات
  $route = Mockery::mock(\Illuminate\Routing\Route::class);
  $route->shouldReceive('parameter')
    ->with('collectionSlug', null)
    ->andReturn('slug-that-does-not-exist');

  $request->setRouteResolver(fn() => $route);

  // 2. إرسال بيانات عادية (لا تهمنا هنا، المهم أنها لا تكسر القواعد الأساسية)
  $data = ['name' => 'Valid Name'];

  // 3. تنفيذ الـ Validator
  $validator = Validator::make($data, $request->rules());
  $request->withValidator($validator);

  // 4. التأكد من أن الـ validator لم يضف أي خطأ إلى 'conditions'
  // لأن الكود دخل في الـ return ولم يكمل للشرط الذي يضيف الخطأ
  expect($validator->errors()->has('conditions'))->toBeFalse();
});
