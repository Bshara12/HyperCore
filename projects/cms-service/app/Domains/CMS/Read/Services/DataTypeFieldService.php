<?php

namespace App\Domains\CMS\Read\Services;

use App\Domains\CMS\Read\Actions\Field\IndexFieldsAction;
use App\Domains\CMS\Read\Actions\Field\IndexTrashedFields;
use App\Models\DataType;

class DataTypeFieldService
{

  public function __construct(
    protected IndexFieldsAction $IndexFieldsAction,
    protected IndexTrashedFields $indexTrashedFieldsAction,
  ) {}

  public function list(DataType $dataType)
  {
    return $this->IndexFieldsAction->execute($dataType);
  }

  public function trashed(DataType $dataType)
  {
    return $this->indexTrashedFieldsAction->execute($dataType);
  }
}
