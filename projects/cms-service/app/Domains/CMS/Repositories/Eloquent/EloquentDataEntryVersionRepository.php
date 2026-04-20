<?php

namespace App\Domains\CMS\Repositories\Eloquent;

use Illuminate\Support\Facades\DB;
use App\Domains\CMS\Repositories\Interface\DataEntryVersionRepository;

class EloquentDataEntryVersionRepository implements DataEntryVersionRepository
{
    public function create(
        int $entryId,
        int $version,
        array $snapshot,
        ?int $userId
    ): void {
        DB::table('data_entry_versions')->insert([
            'data_entry_id' => $entryId,
            'version_number' => $version,
            'snapshot' => json_encode($snapshot),
            'created_by' => $userId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
