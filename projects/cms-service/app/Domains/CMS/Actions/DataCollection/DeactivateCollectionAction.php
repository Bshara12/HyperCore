<?php

namespace App\Domains\CMS\Actions\DataCollection;

use App\Domains\CMS\Repositories\Interface\DataCollectionRepositoryInterface;
use App\Domains\Core\Actions\Action;
use App\Events\SystemLogEvent;

class DeactivateCollectionAction extends Action
{
  protected function circuitServiceName(): string
  {
    return 'dataCollection.deactivate';
  }

  public function __construct(
    protected DataCollectionRepositoryInterface $repository
  ) {}

  public function execute($dto)
  {
    event(new SystemLogEvent(
      module: 'cms',
      eventType: 'deactivate_collection',
      userId: null,
      entityType: 'collection',
      entityId: $dto->slug??null
    ));
    return $this->run(function () use ($dto) {
      return $this->repository->deactivate($dto);
    });
  }
}
