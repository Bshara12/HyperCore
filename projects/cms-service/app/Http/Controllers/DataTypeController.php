<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Domains\CMS\DTOs\DataType\CreateDataTypeDTO;
use App\Domains\CMS\DTOs\DataType\UpdateDataTypeDTO;
use App\Domains\CMS\Read\DTOs\DataType\ShowDataTypeDTOProperities;
use App\Domains\CMS\Read\Services\DataTypeReadService;
use App\Domains\CMS\Services\DataTypeService;
use App\Domains\CMS\Requests\CreateDataTypeRequest;
use App\Domains\CMS\Requests\UpdateDataTypeRequest;
use App\Models\DataType;

class DataTypeController extends Controller
{

  protected $service;
  protected $readService;

  public function __construct(DataTypeService $service, DataTypeReadService $readService)
  {
    $this->service = $service;
    $this->readService = $readService;
  }

  public function index()
  {
    $types = $this->readService->list();
    return response()->json($types);
  }

  public function store(CreateDataTypeRequest $request)
  {
    $dto = CreateDataTypeDTO::fromRequest($request);
    $created = $this->service->create($dto);

    return response()->json([
      'message' => 'DataType created successfully',
      'data' => $created
    ], 201);
  }

  public function show(string $slug)
  {
    $dto = ShowDataTypeDTOProperities::fromRequest($slug);
    $type = $this->readService->findBySlug($dto);

    if (!$type) {
      return response()->json(['message' => 'DataType not found'], 404);
    }

    return response()->json($type);
  }

  public function update(
    DataType $dataType,
    UpdateDataTypeRequest $request
  ) {
    $dto = UpdateDataTypeDTO::fromRequest($request);
    $updated = $this->service->update($dataType, $dto);

    return response()->json([
      'message' => 'DataType updated successfully',
      'data' => $updated
    ]);
  }

  public function destroy(DataType $dataType)
  {
    $this->service->delete($dataType);

    return response()->json([
      'message' => 'DataType deleted successfully'
    ]);
  }

  public function restore($dataTypeId)
  {
    $this->service->restore($dataTypeId);

    return response()->json([
      'message' => 'DataType restored successfully'
    ]);
  }

  public function forceDelete($dataTypeId)
  {
    $this->service->forceDelete($dataTypeId);

    return response()->json([
      'message' => 'DataType force deleted successfully'
    ]);
  }

  public function trashed()
  {
    $project = app('currentProject');
    $trashed = $this->readService->trashed($project->id);

    if ($trashed->isEmpty()) {
      return response()->json([
        'message' => 'No trashed DataTypes found'
      ], 404);
    }
    return response()->json($trashed);
  }
}
