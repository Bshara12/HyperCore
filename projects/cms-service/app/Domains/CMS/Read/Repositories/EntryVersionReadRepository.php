<?php

namespace App\Domains\CMS\Read\Repositories;

use Illuminate\Support\Facades\DB;

class EntryVersionReadRepository implements EntryVersionReadRepositoryInterface
{
    public function listForEntrySlug(
        int $projectId,
        string $entrySlug,
        int $page,
        int $perPage,
        bool $withSnapshot
    ): array {
        $page = max(1, $page);
        $perPage = max(1, min(200, $perPage));

        $query = DB::table('data_entry_versions as v')
            ->join('data_entries as e', 'e.id', '=', 'v.data_entry_id')
            ->where('e.project_id', $projectId)
            ->where('e.slug', $entrySlug)
            ->orderByDesc('v.version_number')
            ->orderByDesc('v.id');

        $total = (clone $query)->count();

        $select = [
            'v.id',
            'v.data_entry_id',
            'v.version_number',
            'v.created_by',
            'v.created_at',
        ];

        if ($withSnapshot) {
            $select[] = 'v.snapshot';
        }

        $items = $query
            ->select($select)
            ->offset(($page - 1) * $perPage)
            ->limit($perPage)
            ->get()
            ->map(fn ($row) => (array) $row)
            ->toArray();

        return [
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'items' => $items,
        ];
    }
}
