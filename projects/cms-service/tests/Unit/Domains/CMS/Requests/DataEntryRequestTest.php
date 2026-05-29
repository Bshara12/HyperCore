<?php

use App\Domains\CMS\DTOs\Data\CreateDataEntryDTO;
use App\Domains\CMS\Requests\DataEntryRequest;
use App\Models\DataEntry;
use App\Support\CurrentProject;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Validator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;

uses(RefreshDatabase::class);

beforeEach(function () {
  // 1. إنشاء نسخة مزيفة (Mock) من الموديل
  $project = Mockery::mock(App\Models\Project::class);
  $project->shouldReceive('getAttribute')->with('id')->andReturn(1);

  // 2. إخبار لارافل: عندما يُطلب 'currentProject' من الحاوية، أعطنا هذا الـ Mock
  app()->instance('currentProject', $project);
});

test('it prepares title and slug automatically if missing', function () {
  // 1. إنشاء طلب Symfony/Laravel أصلي يحتوي على البيانات
  $symfonyRequest = \Illuminate\Http\Request::create('/api/entries', 'POST', [
    'values' => ['title' => ['en' => 'Hello World']]
  ]);

  // 2. إنشاء الـ FormRequest انطلاقاً من هذا الطلب
  $request = \App\Domains\CMS\Requests\DataEntryRequest::createFrom($symfonyRequest);

  // 3. استدعاء prepareForValidation باستخدام Reflection
  $reflection = new \ReflectionMethod(\App\Domains\CMS\Requests\DataEntryRequest::class, 'prepareForValidation');
  $reflection->setAccessible(true);
  $reflection->invoke($request);

  // 4. التحقق من النتائج
  expect($request->input('title'))->toBe('Hello World')
    ->and($request->input('slug'))->toBe('hello-world');
});

test('it validates conditional status for scheduled at', function () {
  $data = [
    'status' => 'scheduled',
    'scheduled_at' => null // يجب أن يفشل لأن status scheduled
  ];

  $validator = Validator::make($data, (new DataEntryRequest())->rules());

  expect($validator->fails())->toBeTrue()
    ->and($validator->errors()->has('scheduled_at'))->toBeTrue();
});

test('it enforces unique slug per project', function () {
  // إنشاء سجل موجود مسبقاً
  DataEntry::factory()->create([
    'slug' => 'existing-slug',
    'project_id' => 1
  ]);

  $request = new DataEntryRequest();
  $request->setMethod('POST');

  $data = [
    'slug' => 'existing-slug', // slug مكرر
    'title' => 'New Title',
    'values' => ['any' => 'data']
  ];

  $validator = Validator::make($data, $request->rules());

  expect($validator->fails())->toBeTrue()
    ->and($validator->errors()->has('slug'))->toBeTrue();
});

test('it allows valid data in post', function () {
  $request = new DataEntryRequest();
  $request->setMethod('POST');

  $data = [
    'title' => 'New Entry',
    'values' => ['test' => 'data'],
    'status' => 'draft'
  ];

  $validator = Validator::make($data, $request->rules());

  expect($validator->passes())->toBeTrue();
});

test('toDto maps input to DTO correctly', function () {
  $data = [
    'values' => ['title' => 'Test'],
    'seo' => ['title' => 'SEO'],
    'relations' => [],
    'status' => 'draft',
    'scheduled_at' => null
  ];

  $request = DataEntryRequest::createFrom(\Illuminate\Http\Request::create('/', 'POST', $data));
  $dto = $request->toDto();

  expect($dto)->toBeInstanceOf(CreateDataEntryDTO::class)
    ->and($dto->values)->toBe($data['values'])
    ->and($dto->status)->toBe('draft');
});

test('it retrieves entry and dataTypeId from route', function () {
  $entry = DataEntry::factory()->create();
  $request = new DataEntryRequest();

  // 1. إنشاء مسار يحاكي المسار الحقيقي لديك
  $route = (new Route('PUT', '/data-types/{dataType}/entries/{entry}', []))
    ->bind(Illuminate\Http\Request::create('/data-types/1/entries/' . $entry->id, 'PUT'));

  // 2. تعيين المتغيرات (Parameters) مباشرة في الـ Route
  // لارافل تستخدم هذا لربط الـ Model بالمسار
  $route->setParameter('entry', $entry);

  // 3. إسناد المسار للـ Request
  $request->setRouteResolver(fn() => $route);
  $request->setContainer(app());

  expect($request->entry()->id)->toBe($entry->id)
    ->and($request->dataTypeId())->toBe((int) $entry->data_type_id);
});

test('it retrieves entryId by route slug', function () {
  // 1. التأكد من وجود البيانات
  $entry = DataEntry::factory()->create(['slug' => 'my-slug']);

  // 2. إنشاء المسار مع تحديد Action وهمي (مهم جداً لتجنب LogicException)
  $route = new Route('GET', 'test/{entry}', ['uses' => function () {
    return 'ok';
  }]);

  // 3. ربط المسار بالطلب أولاً
  $symfonyRequest = \Illuminate\Http\Request::create('test/my-slug', 'GET');
  $route->bind($symfonyRequest);

  // 4. تعيين البارامترات
  $route->setParameter('entry', 'my-slug');

  // 5. إسناد المسار للطلب
  $request = new DataEntryRequest();
  $request->setRouteResolver(fn() => $route);
  $request->setContainer(app());

  // 6. التنفيذ والتحقق
  expect($request->entryId())->toBe((int) $entry->id);
});

test('it retrieves files from request', function () {
  $file = UploadedFile::fake()->create('test.jpg');

  // الحل: اجعل 'files' مصفوفة تحتوي على الملف، وليس الملف مباشرة
  $symfonyRequest = \Illuminate\Http\Request::create('/', 'POST', [], [], ['files' => [$file]]);
  $request = DataEntryRequest::createFrom($symfonyRequest);

  $files = $request->filesInput();

  expect($files)->toBeArray()
    ->and($files[0]->getClientOriginalName())->toBe('test.jpg');
});

test('it extracts title from fallback array if primary keys are missing', function () {
  $request = new DataEntryRequest();
  $request->setMethod('POST');

  // إرسال بيانات بدون title أو en أو ar، ولكن مع قيمة في مفتاح آخر
  $request->merge([
    'values' => [
      'title' => [
        'en' => null,
        'ar' => '',
        'fr' => 'Bonjour le monde' // هذه القيمة التي يجب أن يلتقطها الـ loop
      ]
    ]
  ]);

  // استخدام Reflection لاستدعاء prepareForValidation
  $reflection = new \ReflectionMethod(DataEntryRequest::class, 'prepareForValidation');
  $reflection->setAccessible(true);
  $reflection->invoke($request);

  // التحقق من أن الكود التقط القيمة من مفتاح فرنسي
  expect($request->input('title'))->toBe('Bonjour le monde')
    ->and($request->input('slug'))->toBe('bonjour-le-monde');
});

test('it returns 0 if entry slug is not found', function () {
  // 1. لا نقوم بإنشاء أي سجل، أو نمرر slug غير موجود
  $request = new DataEntryRequest();

  // 2. محاكاة مسار يحتوي على slug غير موجود
  $route = new Route('GET', 'test/{entry}', ['uses' => function () {
    return 'ok';
  }]);
  $symfonyRequest = \Illuminate\Http\Request::create('test/non-existent-slug', 'GET');
  $route->bind($symfonyRequest);
  $route->setParameter('entry', 'non-existent-slug');

  $request->setRouteResolver(fn() => $route);
  $request->setContainer(app());

  // 3. التحقق من أن النتيجة هي 0 (بما أن (int) null هو 0)
  expect($request->entryId())->toBe(0);
});
