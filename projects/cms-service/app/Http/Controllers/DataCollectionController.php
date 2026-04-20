<?php

namespace App\Http\Controllers;

use App\Domains\CMS\DTOs\DataCollection\CollectionItemsDTO;
use App\Domains\CMS\DTOs\DataCollection\CreateDataCollectionDTO;
use App\Domains\CMS\DTOs\DataCollection\DeactivateCollectionDTO;
use App\Domains\CMS\DTOs\DataCollection\UpdateDataCollectionDTO;
use App\Domains\CMS\Services\DataCollectionService;
use App\Domains\CMS\Requests\CreateDataCollectionRequest;
use App\Domains\CMS\Requests\DeactivateCollectionRequest;
use App\Domains\CMS\Requests\InsertCollectionItemsRequest;
use App\Domains\CMS\Requests\RemoveCollectionItemsRequest;
use App\Domains\CMS\Requests\ReOrderCollectionItemsRequest;
use App\Domains\CMS\Requests\UpdateDataCollectionRequest;
use App\Models\DataCollection;

class DataCollectionController extends Controller
{

  public function __construct(protected DataCollectionService $service) {}

  public function index()
  {
    $collections = $this->service->list(app('currentProject')->public_id);

    if (!$collections) {
      return response()->json([
        'message' => 'No collections found',
      ], 404);
    }

    return response()->json([
      'data' => $collections,
    ]);
  }

  public function store(CreateDataCollectionRequest $request)
  {
    $dto = CreateDataCollectionDTO::fromRequest($request);
    $data = $this->service->create($dto);

    return response()->json([
      'message' => 'Collection created successfully',
      'data' => $data
    ]);
  }

  public function update(UpdateDataCollectionRequest $request, string $collectionSlug)
  {
    $dto = UpdateDataCollectionDTO::fromRequest($request, $collectionSlug);
    $data = $this->service->update($dto);

    return response()->json([
      'message' => 'Collection updated successfully',
      'data' => $data
    ]);
  }

  public function destroy(string $collectionSlug)
  {
    $this->service->delete($collectionSlug);

    return response()->json([
      'message' => 'Collection deleted successfully',
    ]);
  }

  public function show(string $collectionSlug)
  {
    $data = $this->service->show(app('currentProject')->public_id, $collectionSlug);

    if (!$data) {
      return response()->json([
        'message' => 'Collection not found',
      ], 404);
    }

    return response()->json([
      'data' => $data,
    ]);
  }

  public function showById(int $collectionId)
  {
    $data = $this->service->showById($collectionId);

    if (!$data) {
      return response()->json([
        'message' => 'Collection not found',
      ], 404);
    }

    return response()->json([
      'data' => $data,
    ]);
  }

  public function addItems(string $collectionSlug, InsertCollectionItemsRequest $request)
  {
    $dto = CollectionItemsDTO::fromInsertRequest($collectionSlug, $request);
    $this->service->addItems($dto);

    return response()->json([
      'message' => 'Items added successfully',
    ]);
  }

  public function removeItems(string $collectionSlug, RemoveCollectionItemsRequest $request)
  {
    $dto = CollectionItemsDTO::fromRemoveRequest($collectionSlug, $request);
    $this->service->removeItems($dto);

    return response()->json([
      'message' => 'Items removed successfully'
    ]);
  }

  public function reorderItems(string $collectionSlug, ReOrderCollectionItemsRequest $request)
  {
    $dto = CollectionItemsDTO::fromReOrderRequest($collectionSlug, $request);
    $data = $this->service->reOrderItems($dto);

    return response()->json([
      'message' => 'Items sorted successfully',
      'items' => $data
    ]);
  }

  public function getEntries($collectionSlug)
  {
    $entries = $this->service->getEntries(app('currentProject')->public_id, $collectionSlug);
    return response()->json($entries);
  }

  public function deactivate($collectionSlug, DeactivateCollectionRequest $request)
  {
    $dto = DeactivateCollectionDTO::fromRequest($collectionSlug, $request);
    $this->service->deactivate($dto);

    return response()->json([
      'message' => "Collection deactivated successfully"
    ]);
  }
}
