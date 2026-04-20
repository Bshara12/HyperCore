<?php

namespace App\Domains\CMS\Read\Actions;

use App\Domains\CMS\Read\Repositories\EntryProjectReadRepositoryInterface;
use App\Domains\CMS\Read\Repositories\EntryReadRepository;
use App\Domains\CMS\Read\Repositories\EntryRelationRepository;
use App\Domains\CMS\Support\LanguageResolver;

class GetProjectEntriesTreeAction
{
    public function __construct(
        private EntryProjectReadRepositoryInterface $repository,
        private EntryReadRepository $entryRepository,
        private EntryRelationRepository $relationRepository,
        private LanguageResolver $languageResolver
    ) {}

    public function execute(int $projectId, array $filters): array
    {
        $language = $this->languageResolver->resolve($filters['lang'] ?? null);
        $fallback = $this->languageResolver->fallback();

        // 1️⃣ جيب كل entries (IDs فقط)
        $entriesCollection = $this->repository
            ->queryByProject($projectId, $filters)
            ->get();

        $entryIds = $entriesCollection->pluck('id')->toArray();

        // 2️⃣ جيب values
        $entries = $this->entryRepository
            ->findPublishedManyWithValues($entryIds, $language, $fallback);

        // 3️⃣ جيب كل العلاقات مرة وحدة
        $relations = $this->relationRepository->getAllByProject($projectId);

        // 4️⃣ build tree
        return $this->buildTree($entries, $relations);
    }

    private function buildTree(array $entries, array $relations): array
    {
        // 🔹 map entries
        $map = [];
        foreach ($entries as $entry) {
            $entry['children'] = [];
            $map[$entry['id']] = $entry;
        }

        // 🔹 ربط الأبناء
        foreach ($relations as $rel) {
            $parentId = $rel['parent_id'];
            $childId = $rel['child_id'];

            if (isset($map[$parentId]) && isset($map[$childId])) {
                $map[$parentId]['children'][] = &$map[$childId];
            }
        }

        // 🔹 استخراج roots (entries بدون parent)
        $hasParent = [];
        foreach ($relations as $rel) {
            $hasParent[$rel['child_id']] = true;
        }

        $tree = [];
        foreach ($map as $id => $entry) {
            if (!isset($hasParent[$id])) {
                $tree[] = $entry;
            }
        }

        return $tree;
    }
}