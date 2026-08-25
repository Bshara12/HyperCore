<?php

use App\Domains\CMS\Actions\Field\CreateFieldAction;
use App\Domains\CMS\Actions\Field\CreationStrategy\FieldTypeFactory;
use App\Domains\CMS\Actions\Field\CreationStrategy\FieldTypeStrategy;
use App\Domains\CMS\DTOs\Field\CreateFieldDTO;
use App\Domains\CMS\Repositories\Interface\FieldRepositoryInterface;
use App\Models\DataType;
use App\Models\DataTypeField;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;

// استخدام RefreshDatabase بهذا الشكل في Pest
uses(RefreshDatabase::class);

test('it ensures data type relation exists correctly', function () {
  Event::fake();
  // 1. إنشاء بيانات حقيقية — النوعان ضمن نفس المشروع، لأن العلاقة عبر
  //    المشاريع مرفوضة عمداً (كل factory()->create() ينشئ مشروعاً خاصاً به)
  $project = \App\Models\Project::factory()->create();
  $relatedType = DataType::factory()->create(['id' => 99, 'project_id' => $project->id]);
  $currentType = DataType::factory()->create(['id' => 1, 'project_id' => $project->id]);

  // 2. جهز الـ DTO
  $dto = new CreateFieldDTO(1, 'test', 'relation', true, false, [], [
    'relation_type' => 'belongs_to',
    'related_data_type_id' => 99,
    'relation_name' => 'test_relation'
  ]);

  // 3. Mock للـ Repository والـ Factory
  $repoMock = Mockery::mock(FieldRepositoryInterface::class);
  $factoryMock = Mockery::mock(FieldTypeFactory::class);

  $action = new CreateFieldAction($repoMock, $factoryMock);

  // 4. التنفيذ
  $id = $action->ensureDataTypeRelationExists($dto, $dto->settings);

  // 5. التأكد من النتيجة في الـ DB الحقيقية
  $this->assertDatabaseHas('data_type_relations', [
    'id' => $id,
    'related_data_type_id' => 99,
  ]);
});

test('it throws 422 exception when related data type does not exist', function () {
  Event::fake();

  $project = \App\Models\Project::factory()->create();
  DataType::factory()->create(['id' => 1, 'project_id' => $project->id]);

  $dto = new CreateFieldDTO(1, 'test', 'relation', true, false, [], [
    'relation_type' => 'belongs_to',
    'related_data_type_id' => 999, // هذا لن يجده في الـ DB
    'relation_name' => 'test'
  ]);

  // 3. Mock للـ Repository والـ Factory (بما أننا لا نريد اختبارهم هنا، فقط المنطق)
  $repoMock = Mockery::mock(FieldRepositoryInterface::class);
  $factoryMock = Mockery::mock(FieldTypeFactory::class);

  $action = new CreateFieldAction($repoMock, $factoryMock);

  // 4. التنفيذ: الكود سيبحث عن 999 ولن يجده في DB -> سيرجع null -> سيطلق abort(422)
  expect(fn() => $action->ensureDataTypeRelationExists($dto, $dto->settings))
    ->toThrow(\Symfony\Component\HttpKernel\Exception\HttpException::class, 'Related DataType does not exist in this project.');
});

test('it refuses to relate a field to a data type owned by another project', function () {
  Event::fake();

  $mine = \App\Models\Project::factory()->create();
  $theirs = \App\Models\Project::factory()->create();

  DataType::factory()->create(['id' => 1, 'project_id' => $mine->id]);
  DataType::factory()->create(['id' => 77, 'project_id' => $theirs->id]);

  $dto = new CreateFieldDTO(1, 'linked_ref', 'relation', false, false, [], [
    'relation_type' => 'belongs_to',
    'related_data_type_id' => 77,
  ]);

  $action = new CreateFieldAction(
    Mockery::mock(FieldRepositoryInterface::class),
    Mockery::mock(FieldTypeFactory::class)
  );

  expect(fn () => $action->ensureDataTypeRelationExists($dto, $dto->settings))
    ->toThrow(\Symfony\Component\HttpKernel\Exception\HttpException::class, 'Related DataType does not exist in this project.');

  expect(\App\Models\DataTypeRelation::count())->toBe(0);
});

test('it processes relation type field correctly and attaches relation id', function () {
  Event::fake();
  $project = \App\Models\Project::factory()->create();

  // 1. يجب إنشاء الـ DataType الأساسي (الذي يمتلك الحقل)
  $dataType = DataType::factory()->create(['id' => 1, 'project_id' => $project->id]);

  // 2. الـ DataType المرتبط — ضمن نفس المشروع
  $relatedDataType = DataType::factory()->create(['id' => 88, 'project_id' => $project->id]);

  // 3. إعداد الموكات للـ Dependencies
  $repoMock = Mockery::mock(FieldRepositoryInterface::class);
  $factoryMock = Mockery::mock(FieldTypeFactory::class);
  $strategyMock = Mockery::mock(FieldTypeStrategy::class);

  // 4. تجهيز الـ DTO (تأكد أن data_type_id هو 1)
  $dto = new CreateFieldDTO(
    data_type_id: 1,
    name: 'my_relation_field',
    type: 'relation',
    required: true,
    translatable: false,
    validation_rules: [],
    settings: [
      'relation_type' => 'belongs_to',
      'related_data_type_id' => 88 // هذا هو الـ ID الذي سيستخدمه التابع
    ]
  );

  // 5. التوقعات
  $factoryMock->shouldReceive('make')->with('relation')->andReturn($strategyMock);
  $strategyMock->shouldReceive('validateRules')->once();
  $strategyMock->shouldReceive('normalizeSettings')
    ->once()
    ->andReturn([
      'related_data_type_id' => 88,
      'relation_type' => 'belongs_to',
      'relation_name' => 'test_relation'
    ]);

  $repoMock->shouldReceive('ensureFieldIsUnique')->once();

  // تأكد أن الـ Repository سيستلم الـ data_type_relation_id
  $repoMock->shouldReceive('create')
    ->once()
    ->with($dto, Mockery::on(function ($settings) {
      return isset($settings['data_type_relation_id']);
    }))
    ->andReturn(new DataTypeField());

  // 6. التنفيذ
  $action = new CreateFieldAction($repoMock, $factoryMock);
  $action->execute($dto);
});
