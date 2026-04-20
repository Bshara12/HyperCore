<?php

namespace App\Domains\CMS\Read\Services;

use App\Domains\CMS\Read\Actions\DataType\IndexDataTypeAction;
use App\Domains\CMS\Read\Actions\DataType\IndexTrashedDataType;
use App\Domains\CMS\Read\Actions\DataType\ShowDataTypeAction;
use App\Domains\CMS\Read\DTOs\DataType\ShowDataTypeDTOProperities;

class DataTypeReadService
{
  public function __construct(
    private ShowDataTypeAction $showAction,
    private IndexDataTypeAction $indexAction,
    private IndexTrashedDataType $indexTrashedAction
  ) {}

  public function findBySlug(ShowDataTypeDTOProperities $dto)
  {
    return $this->showAction->execute($dto);
  }

  public function list()
  {
    $project_id = app('currentProject')->id;
    return $this->indexAction->execute($project_id);
  }

  public function trashed(int $projectId)
  {
    return $this->indexTrashedAction->execute($projectId);
  }
}
