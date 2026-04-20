<?php

namespace App\Domains\CMS\Services\EntryHierarchy;

use App\Domains\CMS\Repositories\Interface\DataEntryRelationRepository;

class EntryHierarchyBuilder
{
  protected array $visited = [];

  public function __construct(
    protected DataEntryRelationRepository $relationRepository
  ) {
  }

  public function buildFromRootIds(array $rootIds): array
  {
    $components = [];

    foreach (array_unique($rootIds) as $rootId) {
      $components[] = $this->buildNode($rootId);
    }

    return $components;
  }

  protected function buildNode(int $id): EntryComponent
  {
    if (isset($this->visited[$id])) {
      // في حالة وجود دورة، نرجع عقدة بدون توسع إضافي
      return new EntryComposite($id);
    }

    $this->visited[$id] = true;

    $node = new EntryComposite($id);

    $childrenIds = $this->relationRepository->pluckEntryIdsWhereRelatedIs($id);

    foreach (array_unique($childrenIds) as $childId) {
      $node->addChild($this->buildNode($childId));
    }

    return $node;
  }

  public function flattenIds(array $rootIds): array
  {
    $components = $this->buildFromRootIds($rootIds);

    $ids = [];

    foreach ($components as $component) {
      foreach ($component->flatten() as $node) {
        $ids[] = $node->getId();
      }
    }

    return array_values(array_unique($ids));
  }
}
