<?php

namespace App\Domains\CMS\Read\Actions;

use App\Domains\CMS\Read\Repositories\EntryReadRepository;
use App\Domains\CMS\Read\Repositories\EntryTypeReadRepository;
use App\Domains\CMS\Repositories\Interface\DataTypeRepositoryInterface;
use App\Domains\CMS\Support\LanguageResolver;

class GetEntriesByDataTypeSlugAction
{
  public function __construct(
    private DataTypeRepositoryInterface $dataTypeRepository,
    private EntryTypeReadRepository $typeRepository,
    private EntryReadRepository $entryRepository,
    private LanguageResolver $languageResolver
  ) {}

  public function execute(int $projectId, string $slug, array $filters): array
  {
    $language = $this->languageResolver->resolve($filters['lang'] ?? null);
    $fallback = $this->languageResolver->fallback();

    // 1️⃣ جيب data_type_id من slug + project
    $dataTypeId = $this->dataTypeRepository
      ->getIdBySlugAndProject($slug, $projectId);

    if (!$dataTypeId) {
      return [
        'message' => 'Data type not found'
      ];
    }

    // 2️⃣ query entries
    $query = $this->typeRepository->filterPublishedByType(
      dataTypeId: $dataTypeId,
      dateFrom: $filters['date_from'] ?? null,
      dateTo: $filters['date_to'] ?? null,
      fieldId: $filters['field_id'] ?? null,
      searchValue: $filters['search'] ?? null
    );

    // 🔥 مهم: تأكد من project
    $query->where('project_id', $projectId);

    // 3️⃣ paginate
    $entriesCollection = $query->paginate(
      $filters['per_page'] ?? 20,
      ['*'],
      'page',
      $filters['page'] ?? 1
    );

    $entryIds = $entriesCollection->pluck('id')->toArray();

    // 4️⃣ fetch values
    $entries = $this->entryRepository
      ->findPublishedManyWithValues(
        $entryIds,
        $language,
        $fallback
      );

    return [
      'data_type_slug' => $slug,
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
