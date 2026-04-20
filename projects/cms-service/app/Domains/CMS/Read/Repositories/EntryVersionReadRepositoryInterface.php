<?php

namespace App\Domains\CMS\Read\Repositories;

interface EntryVersionReadRepositoryInterface
{
    public function listForEntrySlug(
        int $projectId,
        string $entrySlug,
        int $page,
        int $perPage,
        bool $withSnapshot
    ): array;
}
