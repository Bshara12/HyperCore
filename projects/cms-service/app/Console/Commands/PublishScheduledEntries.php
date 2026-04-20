<?php

namespace App\Console\Commands;

use App\Models\DataEntry;
use Illuminate\Console\Command;

class PublishScheduledEntries extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:publish-scheduled-entries';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
  public function handle()
{
    DataEntry::where('status', 'scheduled')
        ->where('scheduled_at', '<=', now())
        ->chunk(100, function ($entries) {
            foreach ($entries as $entry) {
                $entry->update([
                    'status' => 'published',
                    'published_at' => now(),
                ]);
            }
        });
}

}
