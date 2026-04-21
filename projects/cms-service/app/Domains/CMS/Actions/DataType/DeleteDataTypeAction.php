<?php

namespace App\Domains\CMS\Actions\DataType;

use App\Domains\CMS\Repositories\Interface\DataTypeRepositoryInterface;
use App\Domains\CMS\Support\CacheKeys;
use App\Domains\Core\Actions\Action;
use App\Models\DataType;
use Illuminate\Support\Facades\Cache;

class DeleteDataTypeAction extends Action
{
  protected function circuitServiceName(): string
  {
    return 'dataType.delete';
  }

  public function __construct(
    protected DataTypeRepositoryInterface $repository
  ) {}

  public function execute(DataType $dataType): void
  {
    $this->run(function () use ($dataType) {
      $this->repository->delete($dataType);
      Cache::forget(CacheKeys::dataType($dataType->id));
      Cache::forget(CacheKeys::dataTypeBySlug($dataType->slug, $dataType->project_id));
      Cache::forget(CacheKeys::dataTypes($dataType->project_id));
    });
  }
}
