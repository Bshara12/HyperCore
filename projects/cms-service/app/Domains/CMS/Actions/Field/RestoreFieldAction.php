<?php

namespace App\Domains\CMS\Actions\Field;

use App\Domains\CMS\Repositories\Interface\FieldRepositoryInterface;
use App\Domains\Core\Actions\Action;

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
      return $this->repository->restore($fieldId);
    });
  }
}
