<?php

namespace App\Domains\CMS\Read\Actions;

use App\Domains\CMS\Read\Repositories\EntryReadRepository;
use App\Domains\CMS\Read\Repositories\EntryRelationRepository;
use App\Domains\CMS\Support\LanguageResolver;

class GetEntryWithRelationsAction
{
  public function __construct(
    private EntryReadRepository $entryRepository,
    private EntryRelationRepository $relationRepository,
    private LanguageResolver $languageResolver
  ) {}

  public function execute(int $entryId, ?string $requestedLang): ?array
  {
    $language = $this->languageResolver->resolve($requestedLang);
    $fallback = $this->languageResolver->fallback();

    // 🔹 main entry
    $main = $this->entryRepository
      ->findPublishedWithValues($entryId, $language, $fallback);

    if (!$main) {
      return null;
    }

    // 🔹 parents
    $parentIds = $this->relationRepository->getParentIds($entryId);
    // $parents = [];

    // foreach ($parentIds as $parentId) {
    //     $parent = $this->entryRepository
    //         ->findPublishedWithValues($parentId, $language, $fallback);

    //     if ($parent) {
    //         $parents[] = $parent;
    //     }
    // }
    $parents = $this->entryRepository
      ->findPublishedManyWithValues(
        $parentIds,
        $language,
        $fallback
      );


    // 🔹 children
    $childIds = $this->relationRepository->getChildIds($entryId);
    // $children = [];

    // foreach ($childIds as $childId) {
    //   $child = $this->entryRepository
    //     ->findPublishedWithValues($childId, $language, $fallback);

    //   if ($child) {
    //     $children[] = $child;
    //   }
    // }
    $children = $this->entryRepository
    ->findPublishedManyWithValues(
        $childIds,
        $language,
        $fallback
    );

    return [
      'entry' => $main,
      'parents' => $parents,
      'children' => $children,
    ];
  }
}
