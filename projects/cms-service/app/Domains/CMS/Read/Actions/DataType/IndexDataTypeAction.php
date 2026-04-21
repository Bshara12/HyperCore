<?php

namespace App\Domains\CMS\Read\Actions\DataType;

use App\Domains\CMS\Read\Repositories\DataType\DataTypeRepositoryRead;
use App\Domains\CMS\Support\CacheKeys;
use App\Domains\Core\Actions\Action;
use Illuminate\Support\Facades\Cache;

class IndexDataTypeAction extends Action
{
  protected function circuitServiceName(): string
  {
    return 'dataType.index';
  }

  public function __construct(
    protected DataTypeRepositoryRead $repository
  ) {}

  public function execute(int $project_id)
  {
    return $this->run(function () use ($project_id) {
      return Cache::remember(
        CacheKeys::dataTypes($project_id),
        CacheKeys::TTL_MEDIUM,
        fn() => $this->repository->list($project_id)
      );
    });
  }
}
