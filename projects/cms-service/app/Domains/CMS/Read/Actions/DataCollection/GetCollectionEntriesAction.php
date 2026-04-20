<?php

namespace App\Domains\CMS\Read\Actions\DataCollection;

use App\Domains\CMS\Repositories\Interface\DataCollectionRepositoryInterface;
use App\Domains\CMS\Repositories\Interface\ProjectRepositoryInterface;
use App\Domains\Core\Actions\Action;

class GetCollectionEntriesAction extends Action
{
  protected function circuitServiceName(): string
  {
    return 'dataCollection.getEntries';
  }

  public function __construct(
    protected DataCollectionRepositoryInterface $repository,
    protected ProjectRepositoryInterface $projectRepository
  ) {}

  public function execute(string $projectKey, string $collectionSlug)
  {
    return $this->run(function () use ($projectKey, $collectionSlug) {
      $projectId = $this->projectRepository->findByKey($projectKey)->id;
      $collection = $this->repository->find($projectId, $collectionSlug);
      $entries = $this->repository->getEntries($collection->id);
      return $entries;
    });
  }
}
