<?php

namespace App\Listeners;

use App\Domains\Search\Repositories\Interfaces\SearchIndexRepositoryInterface;
use App\Events\EntryRemovedFromSearch;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

/**
 * RemoveEntryFromSearchListener
 *
 * يستمع لـ EntryRemovedFromSearch ويحذف الـ entry من search_indices.
 *
 * يعمل على queue منفصل (search-indexing) مثل IndexDataEntryListener
 * للحفاظ على consistency في معالجة الفهرس.
 */
class RemoveEntryFromSearchListener implements ShouldQueue
{
    use InteractsWithQueue;

    public string $queue = 'search-indexing';

    public int $tries = 3;

    public int $backoff = 10;

    public function __construct(
        private SearchIndexRepositoryInterface $repository
    ) {}

    public function handle(EntryRemovedFromSearch $event): void
    {
        Log::info('SearchIndex: removing entry from index', [
            'entry_id' => $event->entryId,
            'reason'   => $event->reason,
        ]);

        $this->repository->deleteByEntryId($event->entryId);

        Log::info('SearchIndex: entry removed successfully', [
            'entry_id' => $event->entryId,
        ]);
    }

    public function failed(EntryRemovedFromSearch $event, \Throwable $exception): void
    {
        Log::error('SearchIndex: remove listener failed permanently', [
            'entry_id' => $event->entryId,
            'reason'   => $event->reason,
            'error'    => $exception->getMessage(),
        ]);
    }
}