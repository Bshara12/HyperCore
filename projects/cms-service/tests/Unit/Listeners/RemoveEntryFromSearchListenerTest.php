<?php

use App\Listeners\RemoveEntryFromSearchListener;
use App\Events\EntryRemovedFromSearch;
use App\Domains\Search\Repositories\Interfaces\SearchIndexRepositoryInterface;
use Illuminate\Support\Facades\Log;

beforeEach(function () {
  $this->repository = Mockery::mock(SearchIndexRepositoryInterface::class);
  $this->listener = new RemoveEntryFromSearchListener($this->repository);
});

afterEach(function () {
  Mockery::close();
});

test('it deletes entry from search index and logs info successfully on handle', function () {
  // مراقبة سجلات الـ Log المتوقعة أثناء المعالجة
  Log::shouldReceive('info')
    ->once()
    ->with('SearchIndex: removing entry from index', [
      'entry_id' => 123,
      'reason'   => 'deleted',
    ]);

  Log::shouldReceive('info')
    ->once()
    ->with('SearchIndex: entry removed successfully', [
      'entry_id' => 123,
    ]);

  // التأكد من استدعاء ميثود الحذف في الـ Repository بالـ ID الصحيح
  $this->repository->shouldReceive('deleteByEntryId')
    ->once()
    ->with(123);

  $event = new EntryRemovedFromSearch(entryId: 123, reason: 'deleted');

  $this->listener->handle($event);
});

test('it logs error when listener fails permanently', function () {
  // مراقبة سجل الخطأ عند فشل الـ Listener نهائياً
  Log::shouldReceive('error')
    ->once()
    ->with('SearchIndex: remove listener failed permanently', [
      'entry_id' => 456,
      'reason'   => 'unpublished',
      'error'    => 'Database connection error',
    ]);

  $event = new EntryRemovedFromSearch(entryId: 456, reason: 'unpublished');
  $exception = new \Exception('Database connection error');

  $this->listener->failed($event, $exception);
});
