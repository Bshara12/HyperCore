<?php

namespace App\Domains\CMS\States;

use App\Models\DataEntry;

class DataEntryStateResolver
{
  public function resolve(DataEntry $entry): DataEntryState
    {
        return match ($entry->status) {
            'draft' => new DraftState(),
            'published' => new PublishedState(),
            default => new DraftState(),
        };
    }
}
