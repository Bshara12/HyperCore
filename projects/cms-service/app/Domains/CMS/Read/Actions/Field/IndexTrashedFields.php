<?php

namespace App\Domains\CMS\Read\Actions\Field;

use App\Domains\CMS\Read\Repositories\Field\FieldRepositoryRead;
use App\Domains\Core\Actions\Action;
use App\Models\DataType;

class IndexTrashedFields extends Action
{
  protected function circuitServiceName(): string
  {
    return 'dataTypeField.indexTrashed';
  }

  public function __construct(
    protected FieldRepositoryRead $repository
  ) {}

  public function execute(DataType $dataType)
  {
    return $this->run(function () use ($dataType) {
      return $this->repository->indexTrashed($dataType);
    });
  }
}
