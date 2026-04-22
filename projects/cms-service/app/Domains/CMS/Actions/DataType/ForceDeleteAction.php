<?php

namespace App\Domains\CMS\Actions\DataType;

use App\Domains\CMS\Repositories\Interface\DataTypeRepositoryInterface;
use App\Domains\Core\Actions\Action;
use App\Events\SystemLogEvent;

class ForceDeleteAction extends Action
{
  protected function circuitServiceName(): string
  {
    return 'dataType.forceDelete';
  }

  public function __construct(
    protected DataTypeRepositoryInterface $repository
  ) {}

  public function execute(int $dataTypeId): void
  {
    $this->run(function () use ($dataTypeId) {
      $this->repository->forceDelete($dataTypeId);
      event(new SystemLogEvent(
        module: 'cms',
        eventType: 'force_delete_datatype',
        userId: null,
        entityType: 'datatype',
        entityId: $dataTypeId
      ));
    });
  }
}
