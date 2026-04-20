<?php

namespace App\Domains\CMS\Read\Actions;

use App\Domains\CMS\Read\DTOs\EntryVersionDTO;
use App\Domains\CMS\Read\DTOs\EntryVersionsListDTO;
use App\Domains\CMS\Read\Repositories\EntryVersionReadRepositoryInterface;

class GetEntryVersionsAction
{
    public function __construct(
        private EntryVersionReadRepositoryInterface $repository,
    ) {}

    public function execute(
        int $projectId,
        string $entrySlug,
        int $page,
        int $perPage,
        bool $withSnapshot
    ): EntryVersionsListDTO {
        $data = $this->repository->listForEntrySlug(
            projectId: $projectId,
            entrySlug: $entrySlug,
            page: $page,
            perPage: $perPage,
            withSnapshot: $withSnapshot
        );

        $items = array_map(function (array $row) use ($withSnapshot) {
            $snapshot = null;
            if ($withSnapshot && array_key_exists('snapshot', $row)) {
                $snapshot = is_string($row['snapshot'])
                    ? json_decode($row['snapshot'], true)
                    : $row['snapshot'];

                if (!is_array($snapshot)) {
                    $snapshot = null;
                }
            }

            return new EntryVersionDTO(
                id: (int) $row['id'],
                data_entry_id: (int) $row['data_entry_id'],
                version_number: (int) $row['version_number'],
                created_by: $row['created_by'] !== null ? (int) $row['created_by'] : null,
                created_at: (string) $row['created_at'],
                snapshot: $snapshot
            );
        }, $data['items']);

        return new EntryVersionsListDTO(
            total: (int) $data['total'],
            page: (int) $data['page'],
            per_page: (int) $data['per_page'],
            items: $items,
        );
    }
}
