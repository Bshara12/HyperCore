<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class IncrementViewCountJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 3;
    public int $timeout = 30;

    /**
     * @param int[]  $entryIds  IDs of entries that appeared in results
     * @param string $language  for targeting correct rows
     */
    public function __construct(
        private readonly array  $entryIds,
        private readonly string $language = 'en',
    ) {}

    public function handle(): void
    {
        if (empty($this->entryIds)) {
            return;
        }

        $ids = array_unique(array_map('intval', $this->entryIds));

        /*
         * Single bulk UPDATE بدل loop
         * WHERE IN → يضرب الـ index على entry_id
         * لا N+1، لا race condition مشاكل
         */
        $affected = DB::table('search_indices')
            ->whereIn('entry_id', $ids)
            ->where('language', $this->language)
            ->increment('view_count');

        Log::debug('IncrementViewCountJob: done', [
            'entry_count' => count($ids),
            'rows_updated'=> $affected,
            'language'    => $this->language,
        ]);
    }

    public function failed(\Throwable $e): void
    {
        Log::warning('IncrementViewCountJob: failed', [
            'error'    => $e->getMessage(),
            'entry_ids'=> array_slice($this->entryIds, 0, 10),
        ]);
    }
}