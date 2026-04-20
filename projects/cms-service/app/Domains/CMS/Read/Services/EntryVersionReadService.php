<?php

namespace App\Domains\CMS\Read\Services;

use App\Domains\CMS\Read\Actions\GetEntryVersionsAction;
use App\Domains\CMS\Read\DTOs\EntryVersionsListDTO;

class EntryVersionReadService
{
    public function __construct(
        private GetEntryVersionsAction $getEntryVersionsAction,
    ) {}

    public function listForEntrySlug(
        int $projectId,
        string $entrySlug,
        int $page = 1,
        int $perPage = 20,
        bool $withSnapshot = false
    ): EntryVersionsListDTO {
        return $this->getEntryVersionsAction->execute(
            projectId: $projectId,
            entrySlug: $entrySlug,
            page: $page,
            perPage: $perPage,
            withSnapshot: $withSnapshot
        );
    }
}
