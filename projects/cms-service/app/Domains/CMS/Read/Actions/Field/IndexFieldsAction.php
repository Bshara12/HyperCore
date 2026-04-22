<?php

namespace App\Domains\CMS\Read\Actions\Field;

use App\Domains\CMS\Read\Repositories\Field\FieldRepositoryRead;
use App\Domains\CMS\Support\CacheKeys;
use App\Domains\Core\Actions\Action;
use App\Models\DataType;
use Illuminate\Support\Facades\Cache;

class IndexFieldsAction extends Action
{
  protected function circuitServiceName(): string
  {
    return 'dataTypeField.indexFields';
  }

  public function __construct(
    protected FieldRepositoryRead $repository
  ) {}

  public function execute(DataType $dataType)
  {
    return $this->run(function () use ($dataType) {
      return Cache::remember(
        CacheKeys::fields($dataType->id),
        CacheKeys::TTL_MEDIUM,
        fn() => $this->repository->list($dataType)
      );
    });
  }
}
