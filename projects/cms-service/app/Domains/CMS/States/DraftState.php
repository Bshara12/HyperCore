<?php

namespace App\Domains\CMS\States;

use App\Models\DataEntry;

class DraftState implements DataEntryState
{
    public function publish(DataEntry $entry): void
    {
        $entry->update([
            'status' => 'published',
            'published_at' => now(),
        ]);
    }

    public function schedule(DataEntry $entry, string $date): void
    {
        $entry->update([
            'status' => 'scheduled',
            'scheduled_at' => $date,
        ]);
    }
}
