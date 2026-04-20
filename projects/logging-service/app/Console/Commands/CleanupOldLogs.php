<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CleanupOldLogs extends Command
{
  /**
   * The name and signature of the console command.
   *
   * @var string
   */
  // protected $signature = 'app:cleanup-old-logs';

  /**
   * The console command description.
   *
   * @var string
   */
  // protected $description = 'Command description';

  /**
   * Execute the console command.
   */

  protected $signature = 'logs:cleanup';
  protected $description = 'Delete old logs';

  public function handle()
  {
    $deleted = DB::table('logs')
      ->where('occurred_at', '<', now()->subDays(90))
      ->delete();

    $auditDeleted = DB::table('audit_logs')
      ->where('occurred_at', '<', now()->subDays(90))
      ->delete();

    $this->info("Deleted $deleted logs");
    $this->info("Deleted $auditDeleted audit logs");
  }
}
