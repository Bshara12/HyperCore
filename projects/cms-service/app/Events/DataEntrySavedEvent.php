<?php

namespace App\Events;

use App\Models\DataEntry;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DataEntrySavedEvent
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly DataEntry $entry
    ) {}
}