<?php

namespace App\Domains\CMS\States;

use App\Models\DataEntry;

interface DataEntryState
{
  public function publish(DataEntry $entry): void;
  public function schedule(DataEntry $entry, string $date): void;
}
