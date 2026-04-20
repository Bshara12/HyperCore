<?php

namespace App\Domains\CMS\Repositories\Interface;

interface DataEntryVersionRepository
{
    public function create(
        int $entryId,
        int $version,
        array $snapshot,
        ?int $userId
    ): void;
}
