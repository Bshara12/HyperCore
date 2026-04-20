<?php

namespace App\Listeners;

use App\Domains\CMS\Services\Versioning\VersionCreator;
use App\Events\EntryChanged;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class CreateVersionListener
{
    public function __construct(
        protected VersionCreator $versionCreator
    ) {}

    public function handle(EntryChanged $event): void
    {
        $event->entry->load('values');

        $this->versionCreator->create(
            $event->entry,
            $event->userId
        );
    }
}
