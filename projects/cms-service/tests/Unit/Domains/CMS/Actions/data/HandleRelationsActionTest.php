<?php

namespace Tests\Unit\Domains\CMS\Actions\data;

use App\Domains\CMS\Actions\data\HandleRelationsAction;
use App\Domains\CMS\Repositories\Interface\DataEntryRelationRepository;
use App\Domains\Core\Services\CircuitBreakerService;
use Mockery;

test('it does nothing if relations are empty', function () {
  // 1. Arrange: إعداد الموك
  $repoMock = Mockery::mock(DataEntryRelationRepository::class);

  // بما أننا نتوقع عدم استدعاء أي شيء، لن نحتاج لموك الـ CircuitBreaker هنا 
  // ولكن للتأكد من أن الكود لا يصل للمنطقة المحمية:
  $repoMock->shouldNotReceive('deleteForEntry');
  $repoMock->shouldNotReceive('insertForEntry');

  // 2. Act
  $action = new HandleRelationsAction($repoMock);
  $action->execute(1, 10, 100, []); // تمرير مصفوفة فارغة

  // 3. Assert: لا نحتاج لتأكيد شيء لأن Mockery ستتأكد أن الاستدعاءات لم تحدث
});

test('it deletes old and inserts new relations when data is provided', function () {
  // 1. Arrange: إعداد الموك والـ CircuitBreaker
  $circuitMock = Mockery::mock(CircuitBreakerService::class);
  $circuitMock->shouldReceive('canProceed')
    ->with('dataEntry.handleRelations')
    ->andReturn(true);
  $circuitMock->shouldIgnoreMissing();
  app()->instance(CircuitBreakerService::class, $circuitMock);

  $repoMock = Mockery::mock(DataEntryRelationRepository::class);
  $relations = [['id' => 1]];

  // التوقعات
  $repoMock->shouldReceive('deleteForEntry')
    ->once()
    ->with(1); // entryId

  $repoMock->shouldReceive('insertForEntry')
    ->once()
    ->with(1, 10, 100, $relations); // entryId, dataTypeId, projectId, relations

  // 2. Act
  $action = new HandleRelationsAction($repoMock);
  $action->execute(1, 10, 100, $relations);
});
