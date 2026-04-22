<?php

namespace App\Domains\CMS\Actions\Field;

use App\Domains\CMS\Repositories\Interface\FieldRepositoryInterface;
use App\Domains\Core\Actions\Action;
use App\Events\SystemLogEvent;

class RestoreFieldAction extends Action
{
  protected function circuitServiceName(): string
  {
    return 'dataTypeField.restore';
  }

  public function __construct(
    protected FieldRepositoryInterface $repository
  ) {}

  public function execute(int $fieldId)
  {
    return $this->run(function () use ($fieldId) {
      event(new SystemLogEvent(
        module: 'cms',
        eventType: 'restore_field',
        userId: null,
        entityType: 'field',
        entityId: $fieldId
      ));
      return $this->repository->restore($fieldId);
    });
  }
}
