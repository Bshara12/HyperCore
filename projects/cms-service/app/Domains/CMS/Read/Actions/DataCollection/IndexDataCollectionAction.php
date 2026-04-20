<?php

namespace App\Domains\CMS\Read\Actions\DataCollection;

use App\Domains\CMS\Repositories\Interface\DataCollectionRepositoryInterface;
use App\Domains\CMS\Repositories\Interface\ProjectRepositoryInterface;
use App\Domains\Core\Actions\Action;

class IndexDataCollectionAction extends Action
{
  protected function circuitServiceName(): string
  {
    return 'dataCollection.list';
  }

  public function __construct(protected DataCollectionRepositoryInterface $repository, protected ProjectRepositoryInterface $projectRepository) {}

  public function execute($projectId)
  {
    return $this->run(function () use ($projectId) {
      $id = $this->projectRepository->findByKey($projectId)->id;
      return $this->repository->list($id);
    });
  }
}
