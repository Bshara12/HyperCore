<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Domains\E_Commerce\Actions\Offers\ReEvaluateEntryPricesAction;
use Illuminate\Support\Facades\Log;

class ReEvaluateEntryPricesCommand extends Command
{
  protected $signature = 'offers:re-evaluate {entries*}';
  protected $description = 'Re-evaluate entry prices based on active offers';

  public function handle(ReEvaluateEntryPricesAction $action)
  {
    $entries = $this->argument('entries');

    $this->info("Entries sent for re-evaluation: " . implode(', ', $entries));

    $action->execute(array_map(fn($id) => ['entry_id' => $id], $entries));

    $this->info("Re-evaluated prices for " . count($entries) . " entries.");

    return Command::SUCCESS;
  }
}
