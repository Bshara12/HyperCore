<?php

namespace App\Console\Commands;

use App\Domains\E_Commerce\Services\OfferService;
use Illuminate\Console\Command;

class ProcessOffersSchedule extends Command
{
  protected $signature = 'offers:process-schedule';
  protected $description = 'Activate or deactivate offers based on schedule';

  public function handle(OfferService $service)
  {
    $result = $service->run();

    $activatedCount = count($result['activated']);
    $deactivatedCount = count($result['deactivated']);

    $this->info("Activated offers affected entries: {$activatedCount}, Deactivated offers affected entries: {$deactivatedCount}");

    $changedEntries = array_merge($result['activated'], $result['deactivated']);

    if (!empty($changedEntries)) {
      $this->info("Entries sent for re-evaluation: " . implode(', ', $changedEntries));

      $this->call('offers:re-evaluate', ['entries' => $changedEntries]);
    }

    return Command::SUCCESS;
  }
}
