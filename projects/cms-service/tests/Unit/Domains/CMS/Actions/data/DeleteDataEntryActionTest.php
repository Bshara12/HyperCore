<?php

namespace Tests\Unit\Domains\CMS\Actions\data;

use App\Domains\CMS\Actions\data\DeleteDataEntryAction;
use App\Domains\CMS\Actions\data\DeleteEntryFilesAction;
use App\Domains\CMS\Repositories\Interface\DataEntryRelationRepository;
use App\Domains\CMS\Repositories\Interface\DataEntryRepositoryInterface;
use App\Domains\CMS\Repositories\Interface\DataEntryValueRepository;
use App\Domains\CMS\Repositories\Interface\SeoEntryRepository;
use App\Domains\Core\Services\CircuitBreakerService; // أضف هذا
use App\Events\SystemLogEvent;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Mockery;

test('it recursively deletes data entries and associated files/data', function () {
  // 1. Arrange: إعداد التبعيات (Mocks)

  $circuitMock = Mockery::mock(CircuitBreakerService::class);

  // الحل: إخبار الـ Mock أن العملية مسموح بها لهذا الـ service name
  $circuitMock->shouldReceive('canProceed')
    ->with('dataEntry.delete')
    ->andReturn(true);

  // تجاهل أي استدعاءات أخرى (مثل reportFailure أو success)
  $circuitMock->shouldIgnoreMissing();

  app()->instance(CircuitBreakerService::class, $circuitMock);

  $entriesMock = Mockery::mock(DataEntryRepositoryInterface::class);
  $valuesMock = Mockery::mock(DataEntryValueRepository::class);
  $relationsMock = Mockery::mock(DataEntryRelationRepository::class);
  $seoMock = Mockery::mock(SeoEntryRepository::class);
  $deleteFilesMock = Mockery::mock(DeleteEntryFilesAction::class);

  Storage::fake('supabase');
  Event::fake();

  Cache::shouldReceive('forget')->times(8);

  $projectId = 100;

  $parentEntry = Mockery::mock('Entry');
  $parentEntry->shouldReceive('forceDelete')->once();

  $childEntry = Mockery::mock('Entry');
  $childEntry->shouldReceive('forceDelete')->once();

  $entriesMock->shouldReceive('findForProjectOrFail')->with(1, $projectId)->andReturn($parentEntry);
  $relationsMock->shouldReceive('getEntriesWhereRelatedIs')->with(1)->andReturn([['data_entry_id' => 2]]);

  $entriesMock->shouldReceive('findForProjectOrFail')->with(2, $projectId)->andReturn($childEntry);
  $relationsMock->shouldReceive('getEntriesWhereRelatedIs')->with(2)->andReturn([]);

  $deleteFilesMock->shouldReceive('execute')->twice()->andReturn(['path/file1.jpg']);
  $valuesMock->shouldReceive('deleteForEntry')->twice();
  $relationsMock->shouldReceive('deleteForEntry')->twice();
  $relationsMock->shouldReceive('deleteWhereRelatedIs')->twice();
  $seoMock->shouldReceive('deleteForEntry')->twice();

  // 2. Act
  $action = new DeleteDataEntryAction(
    $entriesMock,
    $valuesMock,
    $relationsMock,
    $seoMock,
    $deleteFilesMock
  );

  $action->execute(1, $projectId);

  // 3. Assert
  Event::assertDispatched(SystemLogEvent::class, 2);
  Storage::disk('supabase')->assertMissing('path/file1.jpg');
});
