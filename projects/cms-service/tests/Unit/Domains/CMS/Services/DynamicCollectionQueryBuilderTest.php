<?php

use App\Domains\CMS\Repositories\Interface\DataEntryRelationRepository;
use App\Domains\CMS\Repositories\Interface\DataEntryRepositoryInterface;
use App\Domains\CMS\Repositories\Interface\DataEntryValueRepository;
use App\Domains\CMS\Repositories\Interface\FieldRepositoryInterface;
use App\Domains\CMS\Services\DynamicCollectionQueryBuilder;
use App\Domains\CMS\Services\EntryHierarchy\EntryHierarchyBuilder;
use App\Domains\CMS\Services\ValueConditions\ValueConditionStrategy;
use App\Models\DataCollection;
use App\Models\DataEntry;
use App\Models\DataTypeField;

beforeEach(function () {
  $this->entryRepository = Mockery::mock(DataEntryRepositoryInterface::class);
  $this->relationRepository = Mockery::mock(DataEntryRelationRepository::class);
  $this->valueRepository = Mockery::mock(DataEntryValueRepository::class);
  $this->entryHierarchyBuilder = Mockery::mock(EntryHierarchyBuilder::class);
  $this->fieldRepository = Mockery::mock(FieldRepositoryInterface::class);

  $this->builder = new DynamicCollectionQueryBuilder(
    $this->entryRepository,
    $this->relationRepository,
    $this->valueRepository,
    $this->entryHierarchyBuilder,
    $this->fieldRepository
  );
});

afterEach(function () {
  Mockery::close();
});

// --- الاختبارات الأساسية ---
test('it returns targeted item directly if specified in conditions', function () {
  $collection = new DataCollection(['project_id' => 1, 'data_type_id' => 1, 'conditions' => ['targeted_item' => 99]]);
  $entry = new DataEntry(['id' => 99]);
  $this->entryRepository->shouldReceive('find')->once()->with(99)->andReturn($entry);
  $result = $this->builder->build($collection);
  expect($result->first()->id)->toBe(99);
});

test('it processes AND logic with multiple conditions', function () {
  $collection = new DataCollection([
    'project_id' => 1,
    'data_type_id' => 1,
    'conditions_logic' => 'and',
    'conditions' => [['field' => 'f1', 'operator' => '=', 'value' => 'v1'], ['field' => 'f2', 'operator' => '=', 'value' => 'v2']]
  ]);

  $builder = Mockery::mock(DynamicCollectionQueryBuilder::class, [
    $this->entryRepository,
    $this->relationRepository,
    $this->valueRepository,
    $this->entryHierarchyBuilder,
    $this->fieldRepository
  ])->makePartial()->shouldAllowMockingProtectedMethods();

  $builder->shouldReceive('applyValueConditionReturnEntryIds')->twice()->andReturn([1], [1]);
  $this->entryRepository->shouldReceive('findManyByIds')->once()->with([1])->andReturn([]);
  $builder->build($collection);
});

// --- اختبارات تغطية المسارات الدفاعية (Defensive Coverage) ---
// استبدل اختبار `applyValueConditionReturnEntryIds handles edge cases` بهذه الاختبارات الفردية:

test('applyValueConditionReturnEntryIds returns empty when field is missing', function () {
  // 1. تعيين البيانات المطلوبة لتفادي TypeError
  $reflection = new ReflectionClass(DynamicCollectionQueryBuilder::class);

  $propData = $reflection->getProperty('dataTypeId');
  $propData->setAccessible(true);
  $propData->setValue($this->builder, 1); // تعيين قيمة رقمية (int)

  $propProject = $reflection->getProperty('projectId');
  $propProject->setAccessible(true);
  $propProject->setValue($this->builder, 1);

  // 2. إعداد الموك
  $this->fieldRepository->shouldReceive('findByDataTypeAndName')
    ->once()
    ->with(1, 'unknown') // تأكد أن الـ ID هنا هو 1 (نفس القيمة المعينة أعلاه)
    ->andReturn(null);

  $method = $reflection->getMethod('applyValueConditionReturnEntryIds');
  $method->setAccessible(true);

  expect($method->invokeArgs($this->builder, ['unknown', '=', 'val']))->toBe([]);
});

test('applyValueConditionReturnEntryIds returns empty when relation setting is missing', function () {
  // 1. تعيين البيانات المطلوبة في `$this->builder` الذي تم إنشاؤه في beforeEach
  $reflection = new ReflectionClass(DynamicCollectionQueryBuilder::class);

  $propData = $reflection->getProperty('dataTypeId');
  $propData->setAccessible(true);
  $propData->setValue($this->builder, 1); // 💡 التأكد من استخدام $this->builder

  $propProject = $reflection->getProperty('projectId');
  $propProject->setAccessible(true);
  $propProject->setValue($this->builder, 1);

  // 2. إعداد الموك وتحديد القيمة المتوقعة (1)
  $fieldRel = new \App\Models\DataTypeField();
  $fieldRel->type = 'relation';
  $fieldRel->settings = [];

  $this->fieldRepository->shouldReceive('findByDataTypeAndName')
    ->once()
    ->with(1, 'rel') // 💡 التأكد من تمرير 1 هنا أيضاً
    ->andReturn($fieldRel);

  // 3. استدعاء الميثود
  $method = $reflection->getMethod('applyValueConditionReturnEntryIds');
  $method->setAccessible(true);

  $result = $method->invokeArgs($this->builder, ['rel', '=', 'val']);

  expect($result)->toBe([]);
});

test('applyValueConditionReturnEntryIds returns empty when relatedEntryIds is empty', function () {
  // إعداد الـ Service والبيانات
  $reflection = new ReflectionClass(DynamicCollectionQueryBuilder::class);
  $propData = $reflection->getProperty('dataTypeId');
  $propData->setAccessible(true);
  $propData->setValue($this->builder, 1);
  $propProject = $reflection->getProperty('projectId');
  $propProject->setAccessible(true);
  $propProject->setValue($this->builder, 1);

  // إعداد الموك: الحقل علاقة، لكن الـ Repository لا يجد أي نتائج مرتبطة
  $fieldRel = new \App\Models\DataTypeField();
  $fieldRel->type = 'relation';
  $fieldRel->settings = ['related_data_type_id' => 5];

  $this->fieldRepository->shouldReceive('findByDataTypeAndName')->once()->andReturn($fieldRel);
  $this->entryRepository->shouldReceive('pluckIdsByProjectTypeAndValues')->once()->andReturn([]); // النتيجة فارغة

  $method = $reflection->getMethod('applyValueConditionReturnEntryIds');
  $method->setAccessible(true);

  expect($method->invokeArgs($this->builder, ['rel', '=', 'val']))->toBe([]);
});

test('applyValueConditionReturnEntryIds returns empty when directEntryIds (relation) is empty', function () {
  $reflection = new ReflectionClass(DynamicCollectionQueryBuilder::class);
  $propData = $reflection->getProperty('dataTypeId');
  $propData->setAccessible(true);
  $propData->setValue($this->builder, 1);
  $propProject = $reflection->getProperty('projectId');
  $propProject->setAccessible(true);
  $propProject->setValue($this->builder, 1);

  $fieldRel = new \App\Models\DataTypeField();
  $fieldRel->type = 'relation';
  $fieldRel->settings = ['related_data_type_id' => 5];

  $this->fieldRepository->shouldReceive('findByDataTypeAndName')->once()->andReturn($fieldRel);
  $this->entryRepository->shouldReceive('pluckIdsByProjectTypeAndValues')->once()->andReturn([10]);
  $this->relationRepository->shouldReceive('pluckEntryIdsByRelatedIds')->once()->andReturn([]); // علاقة فارغة

  $method = $reflection->getMethod('applyValueConditionReturnEntryIds');
  $method->setAccessible(true);

  expect($method->invokeArgs($this->builder, ['rel', '=', 'val']))->toBe([]);
});

test('it processes OR logic (union) correctly', function () {
  $collection = new DataCollection(['conditions_logic' => 'or', 'conditions' => [['field' => 'f1', 'operator' => '=', 'value' => 'v1'], ['field' => 'f2', 'operator' => '=', 'value' => 'v2']]]);
  $builder = Mockery::mock(DynamicCollectionQueryBuilder::class, [
    $this->entryRepository,
    $this->relationRepository,
    $this->valueRepository,
    $this->entryHierarchyBuilder,
    $this->fieldRepository
  ])->makePartial()->shouldAllowMockingProtectedMethods();

  $builder->shouldReceive('applyValueConditionReturnEntryIds')->twice()->andReturn([1], [2]);
  $this->entryRepository->shouldReceive('findManyByIds')->once()->with([1, 2])->andReturn([]);
  $builder->build($collection);
});

test('it returns empty collections for edge conditions', function () {
  // اختبار السطر 77-79
  $collection = new DataCollection(['conditions' => [['field' => 'f1', 'operator' => '=', 'value' => 'v1']]]);
  $builder = Mockery::mock(DynamicCollectionQueryBuilder::class, [
    $this->entryRepository,
    $this->relationRepository,
    $this->valueRepository,
    $this->entryHierarchyBuilder,
    $this->fieldRepository
  ])->makePartial()->shouldAllowMockingProtectedMethods();
  $builder->shouldReceive('applyValueConditionReturnEntryIds')->once()->andReturn([]);
  expect($builder->build($collection))->toBeEmpty();

  // اختبار السطر 84-86
  expect($this->builder->build(new DataCollection(['conditions' => []])))->toBeEmpty();
});

test('applyValueConditionReturnEntryIds returns flattened ids for valid relation', function () {
  // 1. التهيئة (نفس المنطق السابق لضبط الـ Reflection)
  $reflection = new ReflectionClass(DynamicCollectionQueryBuilder::class);
  $propData = $reflection->getProperty('dataTypeId');
  $propData->setAccessible(true);
  $propData->setValue($this->builder, 1);
  $propProject = $reflection->getProperty('projectId');
  $propProject->setAccessible(true);
  $propProject->setValue($this->builder, 1);

  // 2. إعداد الـ Mock ليكون مساراً ناجحاً
  $fieldRel = new \App\Models\DataTypeField();
  $fieldRel->type = 'relation';
  $fieldRel->settings = ['related_data_type_id' => 5];

  $this->fieldRepository->shouldReceive('findByDataTypeAndName')->once()->andReturn($fieldRel);
  $this->entryRepository->shouldReceive('pluckIdsByProjectTypeAndValues')->once()->andReturn([10]);
  $this->relationRepository->shouldReceive('pluckEntryIdsByRelatedIds')->once()->andReturn([100]);

  // 3. التوقع أن الميثود flattenIds ستُستدعى وتُرجع قيمة
  $this->entryHierarchyBuilder->shouldReceive('flattenIds')
    ->once()
    ->with([100])
    ->andReturn([999]);

  $method = $reflection->getMethod('applyValueConditionReturnEntryIds');
  $method->setAccessible(true);

  $result = $method->invokeArgs($this->builder, ['rel', '=', 'val']);

  expect($result)->toBe([999]);
});

test('applyValueConditionReturnEntryIds executes strategy for normal field', function () {
  $reflection = new ReflectionClass(DynamicCollectionQueryBuilder::class);
  $propData = $reflection->getProperty('dataTypeId');
  $propData->setAccessible(true);
  $propData->setValue($this->builder, 1);
  $propProject = $reflection->getProperty('projectId');
  $propProject->setAccessible(true);
  $propProject->setValue($this->builder, 1);

  // 1. Mock لحقل ليس من نوع 'relation'
  $fieldText = new \App\Models\DataTypeField();
  $fieldText->type = 'text';
  $this->fieldRepository->shouldReceive('findByDataTypeAndName')->once()->andReturn($fieldText);

  // 2. 💡 استخدام Anonymous Class بدلاً من Mock لضمان نجاح الـ instanceof
  $strategy = new class implements ValueConditionStrategy {
    public function apply($field, $value, $projectId, $dataTypeId): array
    {
      // التحقق من القيم المستلمة
      if ($field === 'some_field' && $value === 'val' && $projectId === 1 && $dataTypeId === 1) {
        return [555];
      }
      return [];
    }
  };

  // 3. حقن الاستراتيجية عبر Reflection
  $propStrategies = $reflection->getProperty('operatorStrategies');
  $propStrategies->setAccessible(true);
  $propStrategies->setValue($this->builder, ['=' => $strategy]);

  $method = $reflection->getMethod('applyValueConditionReturnEntryIds');
  $method->setAccessible(true);

  $result = $method->invokeArgs($this->builder, ['some_field', '=', 'val']);

  expect($result)->toBe([555]);
});

test('applyValueConditionReturnEntryIds returns empty when strategy is invalid', function () {
  $reflection = new ReflectionClass(DynamicCollectionQueryBuilder::class);

  // ضبط الخصائص المحمية (البيئة)
  $propData = $reflection->getProperty('dataTypeId');
  $propData->setAccessible(true);
  $propData->setValue($this->builder, 1);

  $propProject = $reflection->getProperty('projectId');
  $propProject->setAccessible(true);
  $propProject->setValue($this->builder, 1);

  // 1. Mock لحقل ليس من نوع 'relation'
  $fieldText = new \App\Models\DataTypeField();
  $fieldText->type = 'text';
  $this->fieldRepository->shouldReceive('findByDataTypeAndName')->once()->andReturn($fieldText);

  // 2. 💡 حقن كائن غير صالح (stdClass لا يطبق ValueConditionStrategy)
  // هذا سيجعل شرط (! $strategy instanceof ValueConditionStrategy) يتحقق (true)
  $invalidStrategy = new stdClass();

  $propStrategies = $reflection->getProperty('operatorStrategies');
  $propStrategies->setAccessible(true);
  $propStrategies->setValue($this->builder, ['=' => $invalidStrategy]);

  // 3. التنفيذ
  $method = $reflection->getMethod('applyValueConditionReturnEntryIds');
  $method->setAccessible(true);

  $result = $method->invokeArgs($this->builder, ['some_field', '=', 'val']);

  // 4. التأكد من النتيجة المتوقعة (مصفوفة فارغة)
  expect($result)->toBe([]);
});
