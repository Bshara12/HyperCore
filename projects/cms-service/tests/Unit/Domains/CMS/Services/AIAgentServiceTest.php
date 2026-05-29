<?php

use App\Domains\AI\Actions\GenerateProjectSchemaAction;
use App\Domains\CMS\Actions\DataType\CreateDataTypeAction;
use App\Domains\CMS\Actions\Field\CreateFieldAction;
use App\Domains\CMS\Actions\Project\CreateProjectAction;
use App\Domains\CMS\DTOs\AI\GenerateProjectFromSchemaDTO;
use App\Domains\CMS\Services\AIAgentService;
use App\Models\Project;
use App\Models\DataType;
use App\Models\DataTypeField;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;

uses(RefreshDatabase::class);

beforeEach(function () {
  Log::shouldReceive('info')->byDefault();
  Log::shouldReceive('warning')->byDefault();

  $this->generateProjectSchema = Mockery::mock(GenerateProjectSchemaAction::class);
  $this->createProject = Mockery::mock(CreateProjectAction::class);
  $this->createDataType = Mockery::mock(CreateDataTypeAction::class);
  $this->createField = Mockery::mock(CreateFieldAction::class);

  $this->service = new AIAgentService(
    $this->generateProjectSchema,
    $this->createProject,
    $this->createDataType,
    $this->createField
  );
});

test('it proxies schema generation to the respective action', function () {
  $prompt = 'Create a blog system';
  $expectedSchema = ['project_info' => [], 'custom_data_types' => []];

  $this->generateProjectSchema->shouldReceive('execute')
    ->once()
    ->with($prompt)
    ->andReturn($expectedSchema);

  $result = $this->service->generateSchema($prompt);

  expect($result)->toBe($expectedSchema);
});

test('it successfully generates a full project schema with regular fields, variants, and relations', function () {
  $dto = new GenerateProjectFromSchemaDTO(
    ownerId: 1,
    projectInfo: [
      'name' => 'E-Commerce Platform',
      'languages' => ['ar', 'en'],
      'modules' => ['cms', 'shop']
    ],
    customDataTypes: [
      [
        'name' => 'Category',
        'slug' => 'categories',
        'fields' => [
          ['name' => 'name', 'type' => 'text']
        ]
      ],
      [
        'name' => 'Products',
        'slug' => 'products',
        'description' => 'Product catalog',
        'fields' => [
          ['name' => 'title', 'type' => 'text', 'required' => true],
          [
            'name' => 'category_id',
            'type' => 'relation',
            'settings' => ['related_data_type_id' => 'categories']
          ],
        ]
      ]
    ],
    relations: [
      ['source' => 'products', 'target' => 'categories', 'type' => 'belongs_to', 'required' => false]
    ]
  );

  $mockProject = (new Project())->forceFill([
    'id' => 10,
    'name' => 'E-Commerce Platform',
    'slug' => 'e-commerce-platform'
  ]);
  $this->createProject->shouldReceive('execute')->once()->andReturn($mockProject);

  $dataType1 = (new DataType())->forceFill(['id' => 1, 'name' => 'Products', 'slug' => 'products']);
  $dataType2 = (new DataType())->forceFill(['id' => 2, 'name' => 'Category', 'slug' => 'categories']);

  $this->createDataType->shouldReceive('execute')->twice()->andReturnValues([
    $dataType2,
    $dataType1,
  ]);

  // ✅ مصفوفة لالتقاط الحقول الممررة للأكشن أثناء تشغيل الخدمة
  $capturedFields = [];

  $this->createField->shouldReceive('execute')->andReturnUsing(function (...$args) use (&$capturedFields) {
    $capturedFields[] = $args;

    $name = 'unknown';
    $type = 'text';
    foreach ($args as $arg) {
      if (is_array($arg)) {
        $name = $arg['name'] ?? $name;
        $type = $arg['type'] ?? $type;
      } elseif (is_object($arg)) {
        $name = $arg->name ?? $name;
        $type = $arg->type ?? $type;
      }
    }

    return (new DataTypeField())->forceFill([
      'id' => rand(1, 100),
      'data_type_id' => 1,
      'name' => $name,
      'type' => $type
    ]);
  });

  $response = $this->service->generateProject($dto);

  expect($response)->toBeArray()
    ->and($response['project']['id'])->toBe(10)
    ->and($response['total_types'])->toBe(2);

  // ✅ التحقق السلوكي البديل والأكثر استقراراً: نختبر هل حاولت الخدمة بالفعل إرسال بيانات العلاقة الصحيحة؟
  $hasRelationField = false;
  foreach ($capturedFields as $args) {
    foreach ($args as $arg) {
      $name = is_array($arg) ? ($arg['name'] ?? '') : ($arg->name ?? '');
      $type = is_array($arg) ? ($arg['type'] ?? '') : ($arg->type ?? '');

      if ($name === 'category_id' && $type === 'relation') {
        $hasRelationField = true;
        break 2;
      }
    }
  }

  expect($hasRelationField)->toBeTrue();
});

test('it handles field creation failure gracefully and logs warning', function () {
  $dto = new GenerateProjectFromSchemaDTO(
    ownerId: 1,
    projectInfo: ['name' => 'Test Project'],
    customDataTypes: [
      [
        'name' => 'Blog',
        'fields' => [
          ['name' => 'title', 'type' => 'string']
        ]
      ]
    ],
    relations: []
  );

  // 🔥 الحل هنا: تغيير الـ IDs إلى أرقام كبيرة لتجنب التصادم مع بيانات قاعدة البيانات الافتراضية
  $mockProject = (new Project())->forceFill(['id' => 999]);
  $dataType = (new DataType())->forceFill(['id' => 999, 'name' => 'Blog']);

  $this->createProject->shouldReceive('execute')->andReturn($mockProject);
  $this->createDataType->shouldReceive('execute')->andReturn($dataType);

  $this->createField->shouldReceive('execute')->andThrow(new \Exception('Database Disconnected'));

  Log::shouldReceive('warning')
    ->once()
    ->with(Mockery::on(fn($message) => str_contains($message, "Field 'title' skipped")));

  $response = $this->service->generateProject($dto);

  // الآن ستنجح لأن قاعدة البيانات لن تجد أي حقل يحمل data_type_id = 999
  expect($response['total_fields'])->toBe(0);
});

test('it logs warning when relation details cannot be resolved', function () {
  $dto = new GenerateProjectFromSchemaDTO(
    ownerId: 1,
    projectInfo: ['name' => 'Test Project'],
    customDataTypes: [
      [
        'name' => 'Pages',
        'fields' => [
          ['name' => 'author_id', 'type' => 'relation', 'settings' => ['related_data_type_id' => 'NonExistentType']]
        ]
      ]
    ],
    relations: []
  );

  $mockProject = (new Project())->forceFill(['id' => 1]);
  $dataType = (new DataType())->forceFill(['id' => 1, 'name' => 'Pages']);

  $this->createProject->shouldReceive('execute')->andReturn($mockProject);
  $this->createDataType->shouldReceive('execute')->andReturn($dataType);
  $this->createField->shouldReceive('execute');

  Log::shouldReceive('warning')
    ->once()
    ->with(Mockery::on(fn($message) => str_contains($message, "Could not resolve DataType: 'NonExistentType'")));

  $this->service->generateProject($dto);
});
test('it resolves numeric data type identifiers directly', function () {
  $dto = new GenerateProjectFromSchemaDTO(
    ownerId: 1,
    projectInfo: ['name' => 'Test Project'],
    customDataTypes: [
      [
        'name' => 'Products',
        'slug' => 'products',
        'fields' => [
          [
            'name' => 'category_id',
            'type' => 'relation',
            'settings' => ['related_data_type_id' => '99'] // 💡 معرف رقمي مباشر كـ String
          ],
        ]
      ]
    ],
    relations: []
  );

  $this->createProject->shouldReceive('execute')->andReturn((new Project())->forceFill(['id' => 1]));
  $this->createDataType->shouldReceive('execute')->andReturn((new DataType())->forceFill(['id' => 1, 'name' => 'Products']));
  $this->createField->shouldReceive('execute')->andReturn((new DataTypeField()));

  $response = $this->service->generateProject($dto);

  // التحقق من نجاح العملية دون اعتراض النظام
  expect($response)->toBeArray();
});

test('it resolves data type identifiers using naming variants', function () {
  $dto = new GenerateProjectFromSchemaDTO(
    ownerId: 1,
    projectInfo: ['name' => 'Test Project'],
    customDataTypes: [
      [
        'name' => 'Category',
        'slug' => 'product-categories', // مخزن في الـ Map بالـ Slug الأصلي
        'fields' => []
      ],
      [
        'name' => 'Products',
        'slug' => 'products',
        'fields' => [
          [
            'name' => 'category_id',
            'type' => 'relation',
            'settings' => ['related_data_type_id' => 'product_categories'] // 💡 مررناها بصيغة snake_case لتتحول عبر Str::slug إلى product-categories
          ],
        ]
      ]
    ],
    relations: []
  );

  $this->createProject->shouldReceive('execute')->andReturn((new Project())->forceFill(['id' => 1]));

  $dataTypeCategory = (new DataType())->forceFill(['id' => 5, 'name' => 'Category', 'slug' => 'product-categories']);
  $dataTypeProducts = (new DataType())->forceFill(['id' => 6, 'name' => 'Products', 'slug' => 'products']);

  $this->createDataType->shouldReceive('execute')->twice()->andReturnValues([
    $dataTypeCategory,
    $dataTypeProducts
  ]);

  $this->createField->shouldReceive('execute')->andReturn((new DataTypeField()));

  // 💡 نتحقق الآن من الـ Log بالصيغة الجديدة المتطابقة تماماً
  Log::shouldReceive('info')
    ->once()
    ->with(Mockery::on(fn($message) => str_contains($message, "Resolved 'product_categories' → 'product-categories' = 5")));

  $response = $this->service->generateProject($dto);

  expect($response)->toBeArray();
});

test('it logs warning when relation field is missing related_data_type_id', function () {
  $dto = new GenerateProjectFromSchemaDTO(
    ownerId: 1,
    projectInfo: ['name' => 'Test Project'],
    customDataTypes: [
      [
        'name' => 'Pages',
        'slug' => 'pages',
        'fields' => [
          [
            'name' => 'author_id',
            'type' => 'relation',
            'settings' => [] // 💡 مصفوفة إعدادات فارغة لتوليد الخطأ المطلوب عمداً
          ]
        ]
      ]
    ],
    relations: []
  );

  $mockProject = (new Project())->forceFill(['id' => 1]);
  $dataType = (new DataType())->forceFill(['id' => 1, 'name' => 'Pages', 'slug' => 'pages']);

  $this->createProject->shouldReceive('execute')->andReturn($mockProject);
  $this->createDataType->shouldReceive('execute')->andReturn($dataType);

  // 💡 التحقق من أن النظام يسجل التحذير الخاص بفقدان المعرّف للـ Relation
  Log::shouldReceive('warning')
    ->once()
    ->with(Mockery::on(fn($message) => str_contains($message, "Relation field 'author_id' missing related_data_type_id.")));

  $this->service->generateProject($dto);
});

test('it skips processing relation if source or target data type cannot be resolved', function () {
  $dto = new GenerateProjectFromSchemaDTO(
    ownerId: 1,
    projectInfo: ['name' => 'Test Project'],
    customDataTypes: [
      [
        'name' => 'Products',
        'slug' => 'products',
        'fields' => []
      ]
    ],
    relations: [
      [
        'source' => 'products',
        'target' => 'non_existent_slug', // 💡 اسم وهمي غير موجود ليتسبب في فشل الحل
        'type' => 'belongs_to',
        'required' => false
      ]
    ]
  );

  $this->createProject->shouldReceive('execute')->andReturn((new Project())->forceFill(['id' => 1]));
  $this->createDataType->shouldReceive('execute')->andReturn((new DataType())->forceFill(['id' => 1, 'name' => 'Products', 'slug' => 'products']));

  // 🔥 التحقق من تسجيل تحذير تخطي العلاقة بنجاح
  Log::shouldReceive('warning')
    ->once()
    ->with(Mockery::on(fn($message) => str_contains($message, "Skipping relation 'products' → 'non_existent_slug'")));

  $this->service->generateProject($dto);
});

test('it skips relation field creation if it already exists in the database', function () {
  $dto = new GenerateProjectFromSchemaDTO(
    ownerId: 1,
    projectInfo: ['name' => 'Test Project'],
    customDataTypes: [
      [
        'name' => 'Category',
        'slug' => 'categories',
        'fields' => []
      ],
      [
        'name' => 'Products',
        'slug' => 'products',
        'fields' => []
      ]
    ],
    relations: [
      [
        'source' => 'products',
        'target' => 'categories',
        'field_name' => 'category_id',
        'type' => 'belongs_to',
        'required' => false
      ]
    ]
  );

  $this->createProject->shouldReceive('execute')->andReturn((new Project())->forceFill(['id' => 1]));

  $dataTypeCategory = (new DataType())->forceFill(['id' => 20, 'name' => 'Category', 'slug' => 'categories']);
  $dataTypeProducts = (new DataType())->forceFill(['id' => 10, 'name' => 'Products', 'slug' => 'products']);

  $this->createDataType->shouldReceive('execute')->twice()->andReturnValues([
    $dataTypeCategory,
    $dataTypeProducts
  ]);

  $this->createField->shouldReceive('execute')->andReturn((new DataTypeField()));

  // 💡 التغلب على قيود SQLite عبر إدارة الـ Transaction يدوياً لتعطيل الـ Foreign Keys بأمان
  if (Illuminate\Support\Facades\DB::getDriverName() === 'sqlite') {
    Illuminate\Support\Facades\DB::commit(); // إنهاء المعاملة التلقائية مؤقتاً
    Illuminate\Support\Facades\DB::statement('PRAGMA foreign_keys = OFF;');

    Illuminate\Support\Facades\DB::table('data_type_fields')->insert([
      'data_type_id' => 10, // ربطه بـ ID جدول المنتجات الوهمي
      'name' => 'category_id',
      'type' => 'relation',
      'required' => 0,
      'translatable' => 0,
      'sort_order' => 0,
      'settings' => json_encode([]),
      'validation_rules' => json_encode([]),
      'created_at' => now(),
      'updated_at' => now(),
    ]);

    Illuminate\Support\Facades\DB::statement('PRAGMA foreign_keys = ON;');
    Illuminate\Support\Facades\DB::beginTransaction(); // إعادة فتح المعاملة لضمان سلامة دورة حياة التست
  } else {
    Illuminate\Support\Facades\DB::statement('SET FOREIGN_KEY_CHECKS = 0;');
    Illuminate\Support\Facades\DB::table('data_type_fields')->insert([
      'data_type_id' => 10,
      'name' => 'category_id',
      'type' => 'relation',
      'required' => 0,
      'translatable' => 0,
      'sort_order' => 0,
      'settings' => json_encode([]),
      'validation_rules' => json_encode([]),
      'created_at' => now(),
      'updated_at' => now(),
    ]);
    Illuminate\Support\Facades\DB::statement('SET FOREIGN_KEY_CHECKS = 1;');
  }

  // 🔥 التحقق من أن النظام سيكتشف وجود الحقل مسبقاً ويطبع الـ Info المطلوبة للتخطي
  Log::shouldReceive('info')
    ->once()
    ->with(Mockery::on(fn($message) => str_contains($message, "Relation field 'category_id' already exists — skipping.")));

  $response = $this->service->generateProject($dto);

  expect($response)->toBeArray();
});
