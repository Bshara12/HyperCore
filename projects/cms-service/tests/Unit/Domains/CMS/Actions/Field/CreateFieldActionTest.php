<?php

namespace Tests\Unit\Domains\CMS\Actions\Field;

use App\Domains\CMS\Actions\Field\CreateFieldAction;
use App\Domains\CMS\Actions\Field\CreationStrategy\FieldTypeFactory;
use App\Domains\CMS\Actions\Field\CreationStrategy\FieldTypeStrategy;
use App\Domains\CMS\DTOs\Field\CreateFieldDTO;
use App\Domains\CMS\Repositories\Interface\FieldRepositoryInterface;
use App\Models\DataTypeField;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Mockery;

uses(RefreshDatabase::class);

afterEach(function () {
  Mockery::close();
});

test('it creates a field successfully', function () {
  Event::fake();
  // 1. Mock للـ Dependencies
  $repoMock = Mockery::mock(FieldRepositoryInterface::class);
  $factoryMock = Mockery::mock(FieldTypeFactory::class);
  $strategyMock = Mockery::mock(FieldTypeStrategy::class);

  // 2. إعداد الـ DTO
  $dto = new CreateFieldDTO(1, 'test', 'text', true, false, [], []);

  // 3. التوقعات
  $repoMock->shouldReceive('ensureFieldIsUnique')->once();
  $factoryMock->shouldReceive('make')->with('text')->andReturn($strategyMock);
  $strategyMock->shouldReceive('validateRules')->once();
  $strategyMock->shouldReceive('normalizeSettings')->once()->andReturn([]);
  $repoMock->shouldReceive('create')->once()->andReturn(new DataTypeField());

  // 4. التنفيذ
  $action = new CreateFieldAction($repoMock, $factoryMock);
  $result = $action->execute($dto);

  expect($result)->toBeInstanceOf(DataTypeField::class);
});
