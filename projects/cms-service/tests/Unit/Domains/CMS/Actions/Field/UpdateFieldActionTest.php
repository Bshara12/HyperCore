<?php

namespace Tests\Unit\Domains\CMS\Actions\Field;

use App\Domains\CMS\Actions\Field\CreateFieldAction;
use App\Domains\CMS\Actions\Field\CreationStrategy\FieldTypeFactory;
use App\Domains\CMS\Actions\Field\CreationStrategy\FieldTypeStrategy; // تم إضافته
use App\Domains\CMS\Actions\Field\UpdateFieldAction;
use App\Domains\CMS\DTOs\Field\CreateFieldDTO;
use App\Domains\CMS\Repositories\Interface\FieldRepositoryInterface;
use App\Domains\CMS\Support\CacheKeys;
use App\Events\SystemLogEvent;
use App\Models\DataTypeField;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Mockery;

beforeEach(function () {
  $this->mock(\App\Domains\Core\Services\CircuitBreakerService::class, function ($mock) {
    $mock->shouldReceive('canProceed')->andReturn(true)->byDefault();
    $mock->shouldReceive('reportSuccess')->andReturn(true)->byDefault();
    $mock->shouldReceive('reportFailure')->andReturn(true)->byDefault();
  });
});

afterEach(function () {
  Mockery::close();
});

test('it throws 422 if trying to change field type', function () {
  $field = new DataTypeField(['type' => 'text']);
  $dto = new CreateFieldDTO(1, 'name', 'number', true, false, [], []);

  $repoMock = Mockery::mock(FieldRepositoryInterface::class);
  $createFieldMock = Mockery::mock(CreateFieldAction::class);
  // إضافة FactoryMock للـ Constructor
  $factoryMock = Mockery::mock(FieldTypeFactory::class);

  $action = new UpdateFieldAction($repoMock, $createFieldMock, $factoryMock);

  expect(fn() => $action->execute($field, $dto))
    ->toThrow(HttpException::class, 'Changing field type is not allowed.');
});

test('it updates field successfully, clears cache, and logs event', function () {
  $field = new DataTypeField();
  $field->id = 77;
  $field->type = 'text';
  $field->data_type_id = 10;

  $dto = new CreateFieldDTO(10, 'new_name', 'text', true, false, [], []);

  $repoMock = Mockery::mock(FieldRepositoryInterface::class);
  $createFieldActionMock = Mockery::mock(CreateFieldAction::class);

  // استخدام FieldTypeStrategy::class الحقيقي
  $strategyMock = Mockery::mock(FieldTypeStrategy::class);
  $strategyMock->shouldReceive('validateRules')->once();
  $strategyMock->shouldReceive('normalizeSettings')->once()->andReturn([]);

  // Mock للـ Factory بدون Alias
  $factoryMock = Mockery::mock(FieldTypeFactory::class);
  $factoryMock->shouldReceive('make')->with('text')->andReturn($strategyMock);

  $repoMock->shouldReceive('ensureUpdatedFieldIsUnique')
    ->once()
    ->with($dto->data_type_id, $dto->name, $field->id);

  $repoMock->shouldReceive('update')
    ->once()
    ->andReturn($field);

  Cache::spy();
  Event::fake();

  // تمرير الـ 3 معاملات
  $action = new UpdateFieldAction($repoMock, $createFieldActionMock, $factoryMock);
  $result = $action->execute($field, $dto);

  Cache::shouldHaveReceived('forget')->with(CacheKeys::fields($dto->data_type_id));
  Event::assertDispatched(SystemLogEvent::class, function ($event) use ($field) {
    return $event->eventType === 'update_field' && $event->entityId === $field->id;
  });

  expect($result)->toBe($field);
});

test('it updates relation field successfully and handles data type relation verification', function () {
  $field = new DataTypeField();
  $field->id = 77;
  $field->type = 'relation';
  $field->data_type_id = 10;

  $dto = new CreateFieldDTO(
    data_type_id: 10,
    name: 'related_field',
    type: 'relation',
    required: true,
    translatable: false,
    validation_rules: [],
    settings: ['related_data_type_id' => 5]
  );

  $repoMock = Mockery::mock(FieldRepositoryInterface::class);
  $createFieldActionMock = Mockery::mock(CreateFieldAction::class);

  $createFieldActionMock->shouldReceive('ensureDataTypeRelationExists')
    ->once()
    ->with($dto, ['related_data_type_id' => 5])
    ->andReturn(5);

  // استخدام FieldTypeStrategy::class الحقيقي
  $strategyMock = Mockery::mock(FieldTypeStrategy::class);
  $strategyMock->shouldReceive('validateRules')->once();
  $strategyMock->shouldReceive('normalizeSettings')
    ->once()
    ->andReturn(['related_data_type_id' => 5]);

  // Mock للـ Factory بدون Alias
  $factoryMock = Mockery::mock(FieldTypeFactory::class);
  $factoryMock->shouldReceive('make')->with('relation')->andReturn($strategyMock);

  $repoMock->shouldReceive('ensureUpdatedFieldIsUnique')
    ->once()
    ->with($dto->data_type_id, $dto->name, $field->id);

  $repoMock->shouldReceive('update')
    ->once()
    ->with($dto, $field, [
      'related_data_type_id' => 5,
      'data_type_relation_id' => 5
    ])
    ->andReturn($field);

  Cache::spy();
  Event::fake();

  // تمرير الـ 3 معاملات
  $action = new UpdateFieldAction($repoMock, $createFieldActionMock, $factoryMock);
  $result = $action->execute($field, $dto);

  Cache::shouldHaveReceived('forget')->with(CacheKeys::fields($dto->data_type_id));
  Event::assertDispatched(SystemLogEvent::class, function ($event) use ($field) {
    return $event->eventType === 'update_field' && $event->entityId === $field->id;
  });

  expect($result)->toBe($field);
});
