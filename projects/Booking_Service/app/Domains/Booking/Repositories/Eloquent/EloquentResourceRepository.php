<?php

namespace App\Domains\Booking\Repositories\Eloquent;

use App\Domains\Booking\DTOs\AvailabilityDTO;
use App\Domains\Booking\DTOs\CancellationPolicyDTO;
use App\Domains\Booking\DTOs\ResourceDTO;
use App\Domains\Booking\Repositories\Interface\ResourceRepositoryInterface;
use App\Models\BookingCancellationPolicy;
use App\Models\Resource;
use App\Models\ResourceAvailability;
use Illuminate\Database\Eloquent\Collection;

class EloquentResourceRepository implements ResourceRepositoryInterface
{
  // ─── Resource ─────────────────────────────────────────────────────────────

  public function create(ResourceDTO $dto): Resource
  {
    return Resource::create($dto->toCreateArray());
  }

  public function findById(int $id): ?Resource
  {
    return Resource::with([
      'activeAvailabilities',
      'cancellationPolicies',
    ])->find($id);
  }

  public function update(Resource $resource, ResourceDTO $dto): Resource
  {
    $data = $dto->toUpdateArray();

    if (! empty($data)) {
      $resource->update($data);
    }

    return $resource->fresh(['activeAvailabilities', 'cancellationPolicies']);
  }

  public function delete(Resource $resource): void
  {
    $resource->delete();
  }

  public function listByProject(int $projectId): Collection
  {
    return Resource::where('project_id', $projectId)
      ->where('status', Resource::STATUS_ACTIVE)
      ->with(['activeAvailabilities', 'cancellationPolicies'])
      ->get();
  }

  // ─── Availability ─────────────────────────────────────────────────────────

  public function setAvailabilities(Resource $resource, array $dtos): void
  {
    $resource->availabilities()->delete();

    $rows = array_map(fn(AvailabilityDTO $dto) => [
      'resource_id'   => $resource->id,
      'day_of_week'   => $dto->dayOfWeek,
      'start_time'    => $dto->startTime,
      'end_time'      => $dto->endTime,
      'slot_duration' => $dto->slotDuration,
      'is_active'     => $dto->isActive,
      'created_at'    => now(),
      'updated_at'    => now(),
    ], $dtos);

    ResourceAvailability::insert($rows);
  }

  // ─── Cancellation Policy ──────────────────────────────────────────────────

  public function setPolicies(Resource $resource, array $dtos): void
  {
    $resource->cancellationPolicies()->delete();

    $rows = array_map(fn(CancellationPolicyDTO $dto) => [
      'resource_id'       => $resource->id,
      'hours_before'      => $dto->hoursBefore,
      'refund_percentage' => $dto->refundPercentage,
      'description'       => $dto->description,
      'created_at'        => now(),
      'updated_at'        => now(),
    ], $dtos);

    BookingCancellationPolicy::insert($rows);
  }
}
