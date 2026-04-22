<?php

namespace App\Http\Controllers;

use App\Domains\Booking\Services\ResourceService;
use App\Domains\Booking\DTOs\ResourceDTO;
use App\Domains\Booking\Requests\CreateResourceRequest;
use App\Domains\Booking\Requests\SetAvailabilityRequest;
use App\Domains\Booking\Requests\SetCancellationPolicyRequest;
use App\Domains\Booking\Requests\UpdateResourceRequest;
use App\Models\Resource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ResourceController extends Controller
{
  public function __construct(
    private readonly ResourceService $service,
  ) {}

  public function index(Request $request): JsonResponse
  {
    $resources = $this->service->listByProject($request->project_id);

    return response()->json(['data' => $resources]);
  }

  public function show(int $id): JsonResponse
  {
    $resource = $this->service->show($id);

    if (! $resource) {
      return response()->json(['message' => 'Resource not found.'], 404);
    }

    return response()->json(['data' => $resource]);
  }

  public function store(CreateResourceRequest $request): JsonResponse
  {
    $dto = ResourceDTO::fromCreateRequest($request);
    $resource = $this->service->create($dto);

    return response()->json([
      'message' => 'Resource created successfully.',
      'data'    => $resource,
    ], 201);
  }

  public function update(UpdateResourceRequest $request, Resource $resource): JsonResponse
  {
    $dto = ResourceDTO::fromUpdateRequest($request);
    $resource = $this->service->update($resource, $dto);

    return response()->json([
      'message' => 'Resource updated successfully.',
      'data'    => $resource,
    ]);
  }

  public function destroy(Resource $resource): JsonResponse
  {
    $this->service->delete($resource);

    return response()->json(['message' => 'Resource deleted successfully.']);
  }

  public function setAvailability(SetAvailabilityRequest $request, Resource $resource): JsonResponse
  {
    $this->service->setAvailability($resource, $request->availabilities);

    return response()->json([
      'message' => 'Availability updated successfully.',
      'data'    => $resource->fresh(['activeAvailabilities']),
    ]);
  }

  public function setPolicy(SetCancellationPolicyRequest $request, Resource $resource): JsonResponse
  {
    $this->service->setPolicy($resource, $request->policies);

    return response()->json([
      'message' => 'Cancellation policy updated successfully.',
      'data'    => $resource->fresh(['cancellationPolicies']),
    ]);
  }
}
