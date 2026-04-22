<?php

namespace App\Domains\Booking\Services;

use App\Domains\Booking\Actions\CreateResourceAction;
use App\Domains\Booking\Actions\DeleteResourceAction;
use App\Domains\Booking\Actions\SetAvailabilityAction;
use App\Domains\Booking\Actions\SetCancellationPolicyAction;
use App\Domains\Booking\Actions\UpdateResourceAction;
use App\Domains\Booking\DTOs\ResourceDTO;
use App\Domains\Booking\Read\Actions\IndexResourcesAction;
use App\Domains\Booking\Read\Actions\ShowResourceAction;
use App\Domains\Booking\Repositories\Interface\ResourceRepositoryInterface;
use App\Models\Resource;
use Illuminate\Database\Eloquent\Collection;

class ResourceService
{
  public function __construct(
    private readonly CreateResourceAction        $createAction,
    private readonly UpdateResourceAction        $updateAction,
    private readonly SetAvailabilityAction       $availabilityAction,
    private readonly SetCancellationPolicyAction $policyAction,
    private readonly ShowResourceAction $showAction,
    private readonly IndexResourcesAction $indexAction,
    private readonly DeleteResourceAction $deleteAction,
    private readonly ResourceRepositoryInterface $repository,
  ) {}

  public function listByProject(int $projectId): Collection
  {
    return $this->indexAction->execute($projectId);
  }

  public function show(int $id): ?Resource
  {
    return $this->showAction->execute($id);
  }

  public function create(ResourceDTO $dto): Resource
  {
    return $this->createAction->execute($dto);
  }

  public function update(Resource $resource, ResourceDTO $dto): Resource
  {
    return $this->updateAction->execute($resource, $dto);
  }

  public function delete(Resource $resource): void
  {
    $this->deleteAction->execute($resource);
  }

  public function setAvailability(Resource $resource, array $availabilities): void
  {
    $this->availabilityAction->execute($resource, $availabilities);
  }

  public function setPolicy(Resource $resource, array $policies): void
  {
    $this->policyAction->execute($resource, $policies);
  }
}
