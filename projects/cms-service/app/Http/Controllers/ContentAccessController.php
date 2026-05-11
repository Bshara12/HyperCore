<?php

namespace App\Http\Controllers;

use App\Domains\Subscription\DTOs\ContentAccess\ActivateContentAccessDTO;
use App\Domains\Subscription\Services\ContentAccessManagementService;

use App\Domains\Subscription\Requests\ContentAccess\CreateContentAccessRequest;

use App\Domains\Subscription\DTOs\ContentAccess\CreateContentAccessDTO;
use App\Domains\Subscription\DTOs\ContentAccess\UpdateContentAccessMetadataDTO;
use App\Domains\Subscription\Requests\ContentAccess\UpdateContentAccessMetadataRequest;
use App\Models\ContentAccessMetadata;

class ContentAccessController extends Controller
{
  public function __construct(

    private ContentAccessManagementService $service
  ) {}

  public function store(
    CreateContentAccessRequest $request
  ) {

    $dto = CreateContentAccessDTO
      ::fromRequest($request);

    $data = $this->service
      ->create($dto);

    return response()->json([
      'data' => $data
    ], 201);
  }

  public function update(

    UpdateContentAccessMetadataRequest $request,

    ContentAccessMetadata $metadata
  ) {

    $dto =
      UpdateContentAccessMetadataDTO
      ::fromRequest(
        $request,
        $metadata
      );

    $metadata = $this->service
      ->update($dto);

    return response()->json([
      'data' => $metadata
    ]);
  }

  public function destroy(
    ContentAccessMetadata $metadata
  ) {

    $metadata = $this->service
      ->disable($metadata);


    $message = $metadata->wasChanged('is_active')
      ? 'Content access disabled.'
      : 'Content access already disabled.';

    return response()->json([
      'data' => $metadata,
      'message' => $message
    ]);
  }

  public function activate(
    ContentAccessMetadata $metadata
  ) {

    $dto = new ActivateContentAccessDTO(
      $metadata
    );

    $metadata = $this->service
      ->activate($dto);

    return response()->json([
      'data' => $metadata
    ]);
  }

  public function index()
  {

    $projectId = request(
      'project_id'
    );

    $data = $this->service
      ->list($projectId);

    return response()->json(
      $data
    );
  }
  public function show(
    int $id
  ) {

    $data = $this->service
      ->show($id);

    return response()->json([
      'data' => $data
    ]);
  }
}
