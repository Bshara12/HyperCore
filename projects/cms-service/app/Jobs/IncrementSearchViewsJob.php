<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class IncrementSearchViewsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $timeout = 30;

    public function __construct(
        private array $entryIds
    ) {}

    public function handle(): void
    {
        if (empty($this->entryIds)) {
            return;
        }

        // Bulk increment بدل loop
        DB::table('search_indices')
            ->whereIn('entry_id', $this->entryIds)
            ->increment('view_count');
    }
}
