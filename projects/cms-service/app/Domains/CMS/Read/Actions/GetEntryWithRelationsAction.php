<?php

namespace App\Domains\CMS\Read\Actions;

use App\Domains\CMS\Read\Repositories\EntryReadRepository;
use App\Domains\CMS\Read\Repositories\EntryRelationRepository;
use App\Domains\CMS\Read\Services\EntryVisibilityService;
use App\Domains\CMS\Support\LanguageResolver;
use Exception;

class GetEntryWithRelationsAction
{
  public function __construct(
    private EntryReadRepository $entryRepository,
    private EntryRelationRepository $relationRepository,
    private LanguageResolver $languageResolver,
    private EntryVisibilityService $visibilityService
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

    $main =  $this->visibilityService
      ->filterVisible(
        entries:[ $main],
        userId: request()
          ->attributes
          ->get('auth_user')['id']
      );


    if (!$main) {
       throw new Exception("subsicribe to show it");
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
    $parents = $this->visibilityService
      ->filterVisible(
        entries: $parents,
        userId: request()
          ->attributes
          ->get('auth_user')['id']
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
    $children = $this->visibilityService
      ->filterVisible(
        entries: $children,
        userId: request()
          ->attributes
          ->get('auth_user')['id']
      );
    return [
      'entry' => $main,
      'parents' => $parents,
      'children' => $children,
    ];
  }
}
