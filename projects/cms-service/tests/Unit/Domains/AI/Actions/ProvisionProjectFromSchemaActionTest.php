<?php

use App\Domains\AI\Actions\ProvisionProjectFromSchemaAction;
use App\Domains\AI\DTOs\ProvisionProjectFromSchemaDTO;
use App\Domains\CMS\Actions\DataType\CreateDataTypeAction;
use App\Domains\CMS\Actions\Field\CreateFieldAction;
use App\Domains\CMS\Actions\Project\CreateProjectAction;
use App\Domains\CMS\DTOs\Field\CreateFieldDTO;
use App\Domains\CMS\Services\RabbitMQPublisher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

// ─── الدوال المساعدة (Helper Functions) ─────────────────────────

function setupActionWithReflection()
{
  $createFieldMock = test()->mock(CreateFieldAction::class);
  $action = app(ProvisionProjectFromSchemaAction::class);
  $reflection = new \ReflectionClass($action);

  $mapProperty = $reflection->getProperty('dataTypeMap');
  $mapProperty->setAccessible(true);
  $mapProperty->setValue($action, [
    'Category' => 99
  ]);

  $method = $reflection->getMethod('processRelationField');
  $method->setAccessible(true);

  return [$action, $method, $createFieldMock];
}

function setupRelationArrayActionWithReflection()
{
  $action = app(ProvisionProjectFromSchemaAction::class);
  $reflection = new \ReflectionClass($action);

  $mapProperty = $reflection->getProperty('dataTypeMap');
  $mapProperty->setAccessible(true);
  $mapProperty->setValue($action, [
    'Products'   => 1,
    'Categories' => 2,
  ]);

  $method = $reflection->getMethod('processRelationFromArray');
  $method->setAccessible(true);

  return [$action, $method];
}

function setupSanitizeActionWithReflection()
{
  $action = app(ProvisionProjectFromSchemaAction::class);
  $reflection = new \ReflectionClass($action);
  
  $method = $reflection->getMethod('sanitizeValidationRules');
  $method->setAccessible(true);

  return [$action, $method];
}

// ─── تهيئة بيئة قاعدة البيانات والخدمات الخارجية ─────────────────

uses(RefreshDatabase::class);

beforeEach(function () {
    test()->spy(RabbitMQPublisher::class);
});

// ─── الاختبارات الحالية (Existing Test Cases) ───────────────────────────

test('it provisions a full project with types, fields, and relations successfully', function () {
  $data = [
    'project_info' => [
      'name' => 'My New Shop',
      'languages' => ['en'],
      'modules' => ['cms', 'ecommerce'],
    ],
    'custom_data_types' => [
      [
        'name' => 'Product',
        'slug' => 'products',
        'description' => 'A standard product',
        'fields' => [
          ['name' => 'price', 'type' => 'number', 'required' => true],
        ]
      ],
      [
        'name' => 'Category',
        'slug' => 'categories',
        'fields' => []
      ]
    ],
    'relations' => [
      [
        'source' => 'Product',
        'target' => 'Category',
        'type' => 'belongs_to',
        'required' => true
      ]
    ]
  ];

  $dto = ProvisionProjectFromSchemaDTO::fromRequest($data, ownerId: 1);

  $action = app(ProvisionProjectFromSchemaAction::class);
  $result = $action->execute($dto);

  $this->assertDatabaseHas('projects', ['name' => 'My New Shop']);
  $this->assertDatabaseHas('data_types', ['name' => 'Product']);
  $this->assertDatabaseHas('data_types', ['name' => 'Category']);
  $this->assertDatabaseHas('data_type_fields', ['name' => 'price', 'type' => 'number']);
  $this->assertDatabaseHas('data_type_fields', ['name' => 'category_id', 'type' => 'relation']);

  expect($result)->toHaveKey('project')
    ->and($result['total_types'])->toBe(2)
    ->and($result['total_fields'])->toBe(2);
});

test('it resolves data type id correctly covering all variants', function () {
  $action = app(\App\Domains\AI\Actions\ProvisionProjectFromSchemaAction::class);
  $reflection = new \ReflectionClass($action);

  $mapProperty = $reflection->getProperty('dataTypeMap');
  $mapProperty->setAccessible(true);
  $mapProperty->setValue($action, [
    'Brand' => 10,
    'categories' => 20,
    'room_type' => 30,
    'staffMember' => 40,
  ]);

  $resolveMethod = $reflection->getMethod('resolveDataTypeId');
  $resolveMethod->setAccessible(true);

  $resultNumeric = $resolveMethod->invoke($action, '50');
  expect($resultNumeric)->toBe(50);

  $resultExact = $resolveMethod->invoke($action, 'Brand');
  expect($resultExact)->toBe(10);

  $resultSingular = $resolveMethod->invoke($action, 'category');
  expect($resultSingular)->toBe(20);

  $resultPlural = $resolveMethod->invoke($action, 'Brands');
  expect($resultPlural)->toBe(10);

  $resultStudly = $resolveMethod->invoke($action, 'RoomType');
  expect($resultStudly)->toBe(30);

  $resultSnake = $resolveMethod->invoke($action, 'staff_member');
  expect($resultSnake)->toBe(40);

  $resultNull = $resolveMethod->invoke($action, 'GhostType');
  expect($resultNull)->toBeNull();
});

test('it logs warning and returns early if related_data_type_id is missing', function () {
  Log::spy();

  [$action, $method, $createFieldMock] = setupActionWithReflection();

  $fieldData = [
    'name' => 'author',
    'settings' => []
  ];

  $method->invoke($action, 1, $fieldData, 1);

  Log::shouldHaveReceived('warning')
    ->with("[AI Provision] Relation field 'author' missing related_data_type_id.")
    ->once();

  $createFieldMock->shouldNotReceive('execute');
});

test('it logs warning and returns early if related data type cannot be resolved', function () {
  Log::spy();

  [$action, $method, $createFieldMock] = setupActionWithReflection();

  $fieldData = [
    'name' => 'author',
    'settings' => [
      'related_data_type_id' => 'NonExistentType'
    ]
  ];

  $method->invoke($action, 1, $fieldData, 1);

  Log::shouldHaveReceived('warning')
    ->with("[AI Provision] Skipping relation field 'author' — could not resolve 'NonExistentType'.")
    ->once();

  $createFieldMock->shouldNotReceive('execute');
});

test('it resolves string identifier to actual integer id and creates the field', function () {
  $createFieldMock = test()->mock(CreateFieldAction::class);
  $action = app(ProvisionProjectFromSchemaAction::class);

  $reflection = new \ReflectionClass($action);
  $mapProperty = $reflection->getProperty('dataTypeMap');
  $mapProperty->setAccessible(true);
  $mapProperty->setValue($action, [
    'Category' => 99
  ]);

  $fieldData = [
    'name' => 'category_id',
    'type' => 'relation',
    'required' => true,
    'translatable' => false,
    'validation_rules' => [],
    'settings' => [
      'related_data_type_id' => 'Category'
    ]
  ];

  $createFieldMock->shouldReceive('execute')
    ->once()
    ->with(Mockery::on(function (CreateFieldDTO $dto) {
      return $dto->data_type_id === 1
        && $dto->name === 'category_id'
        && $dto->settings['related_data_type_id'] === 99;
    }))
    ->andReturn(new class {
      public $id = 123;
    });

  $method = $reflection->getMethod('processRelationField');
  $method->setAccessible(true);

  $method->invoke($action, 1, $fieldData, 5);
});

test('it logs warning and skips if source or target data type cannot be resolved', function () {
  Log::spy();

  [$action, $method] = setupRelationArrayActionWithReflection();

  $relationData = [
    'source' => 'GhostType',
    'target' => 'Categories',
    'type'   => 'has_many'
  ];

  $method->invoke($action, $relationData);

  Log::shouldHaveReceived('warning')
    ->with("[AI Provision] Skipping relation 'GhostType' → 'Categories'.")
    ->once();
});

test('it logs info and skips if the relation field already exists in database', function () {
  Log::spy();

  DB::rollBack();
  DB::statement('PRAGMA foreign_keys = OFF;');

  [$action, $method] = setupRelationArrayActionWithReflection();

  $fieldName = 'category_id';
  $sourceId  = 1;

  DB::table('data_type_fields')->insert([
    'data_type_id' => $sourceId,
    'name'         => $fieldName,
    'type'         => 'relation',
  ]);

  $relationData = [
    'source'     => 'Products',
    'target'     => 'Categories',
    'field_name' => $fieldName,
    'type'       => 'belongs_to'
  ];

  $method->invoke($action, $relationData);

  Log::shouldHaveReceived('info')
    ->with("[AI Provision] Relation field '{$fieldName}' already exists — skipping.")
    ->once();

  DB::beginTransaction();
});

test('it sanitizes validation rules by removing unallowed rules and keeping allowed ones with their parameters', function () {
  [$action, $method] = setupSanitizeActionWithReflection();

  $rules = [
    'string',
    'max:255',
    'required',
    'numeric',
    'mimes:jpg,png'
  ];

  $result = $method->invoke($action, $rules, 'text');

  expect($result)->toEqual([
    0 => 'string',
    1 => 'max:255',
    2 => 'required'
  ]);
});

test('it returns an empty array if the field type is not defined in allowed types mapping', function () {
  [$action, $method] = setupSanitizeActionWithReflection();

  $rules = ['required', 'string'];

  $result = $method->invoke($action, $rules, 'unknown_type');

  expect($result)->toBeEmpty();
});


// ─── الاختبارات الجديدة المضافة لتغطية الأسطر المطلوبة 🚀 ───────────────────

test('it skips field creation loop if data type id cannot be resolved (Lines 77-79)', function () {
  // تزييف الـ Log والاعتراض سراً لتفريغ الـ Map في منتصف تنفيذ الـ Action
  Log::shouldReceive('info')->andReturnUsing(function ($message) use (&$action) {
    if (str_contains($message, 'DataType created')) {
      $reflection = new \ReflectionClass($action);
      $mapProperty = $reflection->getProperty('dataTypeMap');
      $mapProperty->setAccessible(true);
      $mapProperty->setValue($action, []); // تفريغ الخريطة سراً لتخطي السطر القادم!
    }
  });
  Log::shouldReceive('warning');

  $data = [
    'project_info' => ['name' => 'Skipped Map Project', 'languages' => ['en'], 'modules' => ['cms']],
    'custom_data_types' => [
      [
        'name' => 'GhostType',
        'slug' => 'ghosts',
        'fields' => [
          ['name' => 'title', 'type' => 'text']
        ]
      ]
    ],
    'relations' => []
  ];

  $dto = ProvisionProjectFromSchemaDTO::fromRequest($data, ownerId: 1);
  $action = app(ProvisionProjectFromSchemaAction::class);

  $action->execute($dto);

  // التحقق الحاسم: حقل الـ title يجب ألا يتم إنشاؤه مطلقاً لأن الحلقة قامت بـ continue
  $this->assertDatabaseMissing('data_type_fields', ['name' => 'title']);
});

test('it defers inline relation fields and processes them in pending loop (Lines 85-94 & 102-108)', function () {
  $data = [
    'project_info' => [
      'name' => 'Deferred Relations Project',
      'languages' => ['en'],
      'modules' => ['cms'],
    ],
    'custom_data_types' => [
      [
        'name' => 'Product',
        'slug' => 'products',
        'fields' => [
          ['name' => 'title', 'type' => 'text'],
          [
            'name' => 'category_id', 
            'type' => 'relation', 
            'settings' => ['related_data_type_id' => 'Category']
          ], // حقل علاقة مدمج وسط الحقول العادية لتفعيل الـ pending array
        ]
      ],
      [
        'name' => 'Category',
        'slug' => 'categories',
        'fields' => []
      ]
    ],
    'relations' => []
  ];

  $dto = ProvisionProjectFromSchemaDTO::fromRequest($data, ownerId: 1);
  $action = app(ProvisionProjectFromSchemaAction::class);

  $result = $action->execute($dto);

  // التحقق 1: الحقل العادي تم إنشاؤه بنجاح
  $this->assertDatabaseHas('data_type_fields', ['name' => 'title', 'type' => 'text']);
  
  // التحقق 2: حقل العلاقة المندرج تم تأجيله ومعالجته عبر دالة processRelationField بنجاح
  $this->assertDatabaseHas('data_type_fields', ['name' => 'category_id', 'type' => 'relation']);
  
  // التأكد من مجموع الحقول الكلي المرجع
  expect($result['total_fields'])->toBe(2);
});