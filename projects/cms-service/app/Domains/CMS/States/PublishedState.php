<?php

namespace App\Domains\CMS\States;

use App\Models\DataEntry;
use DomainException;

class PublishedState implements DataEntryState
{
  public function publish(DataEntry $entry): void
  {
    throw new \Exception("Already published.");
  }

  public function schedule(DataEntry $entry, string $date): void
  {
    throw new \Exception("Cannot schedule a published entry.");
  }
}
