<?php

namespace App\Console\Commands;

use App\Models\DataEntry;
use Illuminate\Console\Command;
use App\Events\DataEntrySavedEvent;

// class PublishScheduledEntries extends Command
// {
//     /**
//      * The name and signature of the console command.
//      *
//      * @var string
//      */
//     protected $signature = 'app:publish-scheduled-entries';

//     /**
//      * The console command description.
//      *
//      * @var string
//      */
//     protected $description = 'Command description';

//     /**
//      * Execute the console command.
//      */
//     public function handle()
//     {
//         DataEntry::where('status', 'scheduled')
//             ->where('scheduled_at', '<=', now())
//             ->chunk(100, function ($entries) {
//                 foreach ($entries as $entry) {
//                     $entry->update([
//                         'status' => 'published',
//                         'published_at' => now(),
//                     ]);
//                 }
//             });

//     }
// }


class PublishScheduledEntries extends Command
{
  protected $signature   = 'app:publish-scheduled-entries';
  protected $description = 'Publish scheduled entries whose time has come and index them for search';

  public function handle(): int
  {
    $count = 0;

    DataEntry::where('status', 'scheduled')
      ->where('scheduled_at', '<=', now())
      ->with(['values', 'values.field', 'project']) // eager load للـ indexing
      ->chunk(100, function ($entries) use (&$count) {
        foreach ($entries as $entry) {

          // تحديث الـ status
          $entry->update([
            'status'       => 'published',
            'published_at' => now(),
          ]);

          // إعادة تحميل القيم بعد التحديث
          $entry->refresh();
          $entry->load(['values', 'values.field', 'project']);

          // إطلاق الـ event → IndexDataEntryListener يُفهرسه
          event(new DataEntrySavedEvent($entry));

          $count++;
        }
      });

    $this->info("✓ Published and queued for indexing: {$count} entries.");

    return self::SUCCESS;
  }
}
