<?php

namespace App\Domains\CMS\Read\Actions\DataType;

use App\Domains\CMS\Read\DTOs\DataType\ShowDataTypeDTOProperities;
use App\Domains\CMS\Read\Repositories\DataType\DataTypeRepositoryRead;
use App\Domains\CMS\Support\CacheKeys;
use App\Domains\Core\Actions\Action;
use Illuminate\Support\Facades\Cache;

class ShowDataTypeAction extends Action
{
  protected function circuitServiceName(): string
  {
    return 'dataType.show';
  }

  public function __construct(
    protected DataTypeRepositoryRead $repository
  ) {}

  public function execute(ShowDataTypeDTOProperities $dto)
  {
    return Cache::remember(
      CacheKeys::dataTypeBySlug($dto->slug, $dto->project_id),
      CacheKeys::TTL_LONG,
      fn() => $this->repository->findBySlug($dto->slug, $dto->project_id)
    );
  }
}
