<?php
namespace App\Domains\CMS\Read\Actions;

use App\Domains\CMS\Read\Repositories\EntryProjectReadRepositoryInterface;
use App\Domains\CMS\Read\Repositories\EntryReadRepository;
use App\Domains\CMS\Support\LanguageResolver;

class GetProjectEntriesAction
{
    public function __construct(
        private EntryProjectReadRepositoryInterface $repository,
        private EntryReadRepository $entryRepository,
        private LanguageResolver $languageResolver
    ) {}

    public function execute(int $projectId, array $filters): array
    {
        $language = $this->languageResolver->resolve($filters['lang'] ?? null);
        $fallback = $this->languageResolver->fallback();

        $query = $this->repository->queryByProject(
            projectId: $projectId,
            filters: $filters
        );

        $entriesCollection = $query->paginate(
            $filters['per_page'],
            ['*'],
            'page',
            $filters['page']
        );

        $entryIds = $entriesCollection->pluck('id')->toArray();

        $entries = $this->entryRepository
            ->findPublishedManyWithValues($entryIds, $language, $fallback);

        return [
            'entries' => $entries,
            'meta' => [
                'current_page' => $entriesCollection->currentPage(),
                'last_page' => $entriesCollection->lastPage(),
                'total' => $entriesCollection->total(),
                'per_page' => $entriesCollection->perPage(),
            ]
        ];
    }
}