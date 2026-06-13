<?php

namespace App\Listeners;

use App\Domains\Search\Actions\IndexDataEntryAction;
use App\Domains\Search\Repositories\Interfaces\SearchIndexRepositoryInterface;
use App\Events\DataEntrySavedEvent;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

/**
 * IndexDataEntryListener
 *
 * يستمع لـ DataEntrySavedEvent (create + update) ويُحدّث search_indices.
 *
 * ─── منطق الحالات ─────────────────────────────────────────────────────────
 *
 * published  → فهرَسة كاملة (index)
 * archived   → تحديث status في الفهرس فقط (يبقى مفهرساً لكن لا يظهر في البحث)
 *              لأن EloquentSearchRepository يفلتر: WHERE status = 'published'
 *              هذا يسمح بإعادة نشره بسرعة دون إعادة فهرسة كاملة
 * draft      → لا فهرسة — EntryRemovedFromSearch يُعالجه إذا كان published سابقاً
 * scheduled  → لا فهرسة — سيُفهرس عند النشر الفعلي
 *
 * ─── لماذا لا نحذف archived من الفهرس؟ ──────────────────────────────────
 * إذا حذفنا archived وأعاد المستخدم نشره → نحتاج إعادة فهرسة كاملة.
 * إذا أبقيناه بـ status='archived' → عند إعادة النشر، upsert يُحدّث status فقط.
 * الخيار الثاني أسرع وأقل تكلفة.
 */
class IndexDataEntryListener implements ShouldQueue
{
    use InteractsWithQueue;

    public string $queue = 'search-indexing';

    public int $tries = 3;

    public int $backoff = 10;

    public function __construct(
        private IndexDataEntryAction $indexAction,
        // private SearchIndexRepositoryInterface $repository,
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

        match ($entry->status) {
            // ─── published: فهرسة كاملة ──────────────────────────────────
            'published' => $this->handlePublished($entry),

            // ─── archived: تحديث status فقط بدون إعادة فهرسة المحتوى ─────
            'archived'  => $this->handleArchived($entry),

            // ─── draft/scheduled: لا فهرسة
            // الحذف من الفهرس يُعالَج بـ EntryRemovedFromSearch إذا لزم ─────
            default     => Log::info('SearchIndex: skipping status', [
                'entry_id' => $entry->id,
                'status'   => $entry->status,
            ]),
        };
    }

    // ─────────────────────────────────────────────────────────────────────

    private function handlePublished($entry): void
    {
        $this->indexAction->execute($entry);

        Log::info('SearchIndex: entry indexed successfully', [
            'entry_id' => $entry->id,
        ]);
    }

    private function handleArchived($entry): void
    {
        // تحديث status فقط في كل language rows الموجودة
        // بدون إعادة قراءة المحتوى أو إعادة فهرسة كاملة
        \Illuminate\Support\Facades\DB::table('search_indices')
            ->where('entry_id', $entry->id)
            ->update([
                'status'     => 'archived',
                'updated_at' => now(),
            ]);

        Log::info('SearchIndex: entry archived in index', [
            'entry_id' => $entry->id,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────

    public function failed(DataEntrySavedEvent $event, \Throwable $exception): void
    {
        Log::error('SearchIndex: listener failed permanently', [
            'entry_id' => $event->entry->id,
            'error'    => $exception->getMessage(),
            'trace'    => $exception->getTraceAsString(),
        ]);
    }
}