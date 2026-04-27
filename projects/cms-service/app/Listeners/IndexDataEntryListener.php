<?php

namespace App\Listeners;

use App\Domains\Search\Actions\IndexDataEntryAction;
use App\Events\DataEntrySavedEvent;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class IndexDataEntryListener implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * اسم الـ Queue الذي سيعمل عليه هذا الـ Listener
     * نعزله في queue خاص حتى لا يؤثر على باقي العمليات
     */
    public string $queue = 'search-indexing';

    /**
     * عدد محاولات إعادة التنفيذ عند الفشل
     */
    public int $tries = 3;

    /**
     * وقت الانتظار بين المحاولات (بالثواني)
     */
    public int $backoff = 10;

    public function __construct(
        private IndexDataEntryAction $indexAction
    ) {}

    public function handle(DataEntrySavedEvent $event): void
    {
        $entry = $event->entry;

        Log::info('SearchIndex: received indexing job', [
            'entry_id'     => $entry->id,
            'data_type_id' => $entry->data_type_id,
            'project_id'   => $entry->project_id,
            'status'       => $entry->status,
        ]);

        // فقط الـ entries المنشورة تُفهرس
        // الـ draft و scheduled لا تظهر في نتائج البحث
        if (!in_array($entry->status, ['published', 'archived'], true)) {
            Log::info('SearchIndex: skipping non-published entry', [
                'entry_id' => $entry->id,
                'status'   => $entry->status,
            ]);
            return;
        }

        $this->indexAction->execute($entry);

        Log::info('SearchIndex: entry indexed successfully', [
            'entry_id' => $entry->id,
        ]);
    }

    /**
     * ماذا يحدث عند فشل كل المحاولات؟
     */
    public function failed(DataEntrySavedEvent $event, \Throwable $exception): void
    {
        Log::error('SearchIndex: listener failed permanently', [
            'entry_id' => $event->entry->id,
            'error'    => $exception->getMessage(),
            'trace'    => $exception->getTraceAsString(),
        ]);
    }
}