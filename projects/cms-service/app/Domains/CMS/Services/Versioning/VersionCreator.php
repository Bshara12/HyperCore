<?php

namespace  App\Domains\CMS\Services\Versioning;

use App\Models\DataEntry;
use App\Models\DataEntryVersion;
use Illuminate\Support\Facades\DB;

class VersionCreator
{
    public function __construct(
        protected SnapshotGenerator $snapshotGenerator
    ) {}

    public function create(DataEntry $entry, ?int $userId = null): void
    {
        DB::transaction(function () use ($entry, $userId) {

            $lastVersion = DataEntryVersion::where('data_entry_id', $entry->id)
                ->lockForUpdate()
                ->max('version_number');

            $nextVersion = $lastVersion ? $lastVersion + 1 : 1;

            $snapshot = $this->snapshotGenerator->generate($entry);

            DataEntryVersion::create([
                'data_entry_id' => $entry->id,
                'version_number' => $nextVersion,
                'snapshot' => $snapshot,
                'created_by' => $userId,
            ]);
        });
    }
}
