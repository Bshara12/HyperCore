<?php

namespace App\Domains\Booking\Read\Actions;

use App\Domains\Booking\Repositories\Interface\ResourceRepositoryInterface;
use App\Domains\Booking\Support\CacheKeys;
use App\Domains\Core\Actions\Action;
use App\Models\Resource;
use Illuminate\Support\Facades\Cache;

class ShowResourceAction extends Action
{
  protected function circuitServiceName(): string
  {
    return 'resource.show';
  }

  public function __construct(
    private readonly ResourceRepositoryInterface $repository,
  ) {}

  public function execute(int $id): ?Resource
  {
    return $this->run(function () use ($id) {
      return Cache::remember(
        CacheKeys::resource($id),
        CacheKeys::TTL_LONG,
        fn() => $this->repository->findById($id)
      );
    });
  }
}
