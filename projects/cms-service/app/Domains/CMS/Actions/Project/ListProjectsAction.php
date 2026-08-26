<?php

namespace App\Domains\CMS\Actions\Project;

use App\Domains\CMS\Repositories\Interface\ProjectRepositoryInterface;
use App\Domains\CMS\Support\CacheKeys;
use App\Domains\Core\Actions\Action;
use App\Support\ActingUser;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class ListProjectsAction extends Action
{
  protected function circuitServiceName(): string
  {
    return 'project.index';
  }

  public function __construct(
    private ProjectRepositoryInterface $repository
  ) {}

  /**
   * Only a platform operator (hyper_core) sees every project. Everyone else
   * gets the projects they own or joined.
   *
   * This used to return repository->all() to every caller under one shared
   * cache key, which handed the whole platform's project list — names, slugs
   * and public_ids — to any authenticated user. A public_id is the
   * X-Project-Key every CMS request is scoped by, so that was a live path
   * into other tenants' data, not just a listing leak.
   */
  public function execute(): Collection
  {
    return $this->run(function () {

      if (ActingUser::isHyperCore()) {

        return Cache::remember(
          CacheKeys::allProjects(),
          CacheKeys::TTL_LONG,
          fn() => $this->repository->all()
        );
      }

      $userId = ActingUser::id();

      // No identifiable caller means nothing is scoped to them. Returning the
      // full list here would reopen the hole the branch above closes.
      if (! $userId) {
        return collect();
      }

      // The role grants come from the token payload, so they can change
      // between requests for the same user — they belong in the key.
      $roleProjectIds = ActingUser::roleProjectIds();

      return Cache::remember(
        CacheKeys::userProjects($userId, $roleProjectIds),
        CacheKeys::TTL_LONG,
        fn() => $this->repository->allForUser($userId, $roleProjectIds)
      );
    });
  }
}
