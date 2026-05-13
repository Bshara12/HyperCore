<?php

namespace App\Domains\CMS\Read\Actions;

use App\Domains\CMS\Read\Repositories\EntryTypeReadRepository;
use App\Domains\CMS\Read\Repositories\EntryReadRepository;
use App\Domains\CMS\Read\Services\EntryVisibilityService;
use App\Domains\CMS\Support\LanguageResolver;

// class GetEntriesBySameTypeAction
// {
//   public function __construct(
//     private EntryTypeReadRepository $typeRepository,
//     private EntryReadRepository $entryRepository,
//     private LanguageResolver $languageResolver
//   ) {}

//   public function execute(int $entryId, ?string $requestedLang): ?array
//   {
//     $language = $this->languageResolver->resolve($requestedLang);
//     $fallback = $this->languageResolver->fallback();

//     $dataTypeId = $this->typeRepository->getDataTypeId($entryId);

//     if (!$dataTypeId) {
//       return null;
//     }

//     $entryIds = $this->typeRepository
//       ->getPublishedEntriesByType($dataTypeId);

//     // $entries = [];

//     // foreach ($entryIds as $id) {
//     //   $entry = $this->entryRepository
//     //     ->findPublishedWithValues($id, $language, $fallback);
//     //   if ($entry) {
//     //     $entries[] = $entry;
//     //   }
//     // }
//     $entries = $this->entryRepository
//         ->findPublishedManyWithValues(
//             $entryIds,
//             $language,
//             $fallback
//         );


//     return [
//       'data_type_id' => $dataTypeId,
//       'entries' => $entries,
//     ];
//   }
// }

class GetEntriesBySameTypeAction
{
  public function __construct(
    private EntryTypeReadRepository $typeRepository,
    private EntryReadRepository $entryRepository,
    private LanguageResolver $languageResolver,
    private EntryVisibilityService $visibilityService
  ) {}

  public function execute(
    int $entryId,
    ?string $requestedLang,
    int $page = 1,
    int $perPage = 20,
    bool $all = false,
    ?string $dateFrom = null,
    ?string $dateTo = null,
    ?int $fieldId = null,
    ?string $searchValue = null

  ): ?array {

    $language = $this->languageResolver->resolve($requestedLang);
    $fallback = $this->languageResolver->fallback();

    $dataTypeId = $this->typeRepository->getDataTypeId($entryId);

    if (!$dataTypeId) {
      return null;
    }

    // $query = $this->typeRepository->queryPublishedByType($dataTypeId);
    $query = $this->typeRepository->filterPublishedByType(
      dataTypeId: $dataTypeId,
      dateFrom: $dateFrom,
      dateTo: $dateTo,
      fieldId: $fieldId,
      searchValue: $searchValue
    );

    if ($all) {
      $entriesCollection = $query->get();
    } else {
      $perPage = min($perPage, 100);
      $entriesCollection = $query->paginate($perPage, ['*'], 'page', $page);
    }

    // $entryIds = collect($entriesCollection)->pluck('id')->toArray();
    $entryIds = $entriesCollection->pluck('id')->toArray();

    // $entries = $this->entryRepository
    //   ->findPublishedManyWithValues(
    //     $entryIds,
    //     $language,
    //     $fallback
    //   );
    $entries = $this->entryRepository
      ->findPublishedManyWithValues(
        $entryIds,
        $language,
        $fallback
      );

    $entries = $this->visibilityService
      ->filterVisible(
        entries: $entries,
        userId: request()
          ->attributes
          ->get('auth_user')['id'] ?? null
      );

    return [
      'data_type_id' => $dataTypeId,
      'entries' => $entries,
      'meta' => $all ? null : [
        'current_page' => $entriesCollection->currentPage(),
        'last_page' => $entriesCollection->lastPage(),
        'total' => $entriesCollection->total(),
        'per_page' => $entriesCollection->perPage(),
      ]
    ];
  }
}
